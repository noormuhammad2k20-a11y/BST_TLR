<?php
namespace Tests\Integration;

use App\Models\{User,Customer as TailorCustomer,Order as TailorOrder,Payment,Delivery,Staff,StaffPayment};
use App\Models\ClothStore\{Customer,CustomerPayment,Order,Product,Location,Role,Permission};
use App\Services\{Settings,TailoringFinanceService,OrderService,PricingService,BackupService};
use App\Services\ClothStore\{ReturnService,FinanceService,OrderWorkflow};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class WorkflowCoverageTest extends BusinessIntegrityTest
{
    public function test_login_and_invalid_login(): void
    {
        auth()->logout();$user=User::factory()->create(['password'=>'a-long-test-password','is_active'=>true]);
        $this->post(route('login.attempt'),['email'=>$user->email,'password'=>'wrong-password'])->assertSessionHasErrors('email');
        $this->post(route('login.attempt'),['email'=>$user->email,'password'=>'a-long-test-password'])->assertRedirect();
        $this->assertAuthenticatedAs($user);
    }
    public function test_customer_create_update_validation(): void
    {
        $r=$this->postJson(route('cloth-store.customers.quick'),['name'=>'New customer','phone'=>'03001234567']);
        $r->assertSuccessful();$customer=Customer::where('phone','03001234567')->firstOrFail();
        $this->putJson(route('cloth-store.customers.update',$customer),['name'=>'Updated customer','phone'=>'03001234567'])->assertSuccessful();
        $this->assertSame('Updated customer',$customer->fresh()->name);
    }
    public function test_manager_and_cashier_permissions_use_stored_grants(): void
    {
        $permission=Permission::firstOrCreate(['name'=>'Finance - Reverse Payment'],['module'=>'Finance']);
        $role=Role::create(['name'=>'Test Manager '.uniqid()]);$role->permissions()->attach($permission);
        $manager=User::factory()->create(['role'=>'staff','is_active'=>true]);$manager->csRoles()->attach($role);
        [$c,$p,$o]=$this->sale();$payment=CustomerPayment::where('cs_order_id',$o->id)->firstOrFail();
        $this->actingAs($manager)->putJson(route('cloth-store.payments.reverse',$payment))->assertSuccessful();
        $this->assertSame('100.00',$o->fresh()->remaining_amount);
        $this->getJson(route('cloth-store.settings.index'))->assertForbidden();
    }
    public function test_tailoring_full_partial_payment_and_reversal_keep_original(): void
    {
        Settings::put(['allow_partial'=>true,'auto_delivery_update'=>false]);
        $customer=TailorCustomer::create(['name'=>'Tailoring customer','phone'=>'TC-'.uniqid()]);
        $order=TailorOrder::create(['customer_id'=>$customer->id,'total'=>'100.00','advance'=>'0.00','balance'=>'100.00','status'=>'Pending']);
        $service=app(TailoringFinanceService::class);
        $first=$service->record($order->id,['amount'=>'40.00','payment_method'=>'Cash']);
        $this->assertSame('60.00',$order->fresh()->balance);
        $service->record($order->id,['amount'=>'60.00','payment_method'=>'Cash']);
        $this->assertSame('0.00',$order->fresh()->balance);
        $service->reverse($first->id);$this->assertSame('40.00',$order->fresh()->balance);
        $this->assertNotNull(Payment::find($first->id));$this->assertSame('Reversed',$first->fresh()->status);
        $this->expectException(ValidationException::class);$service->record($order->id,['amount'=>'41.00','payment_method'=>'Cash']);
    }
    public function test_delivery_failure_rolls_back_delivery_row(): void
    {
        $customer=TailorCustomer::create(['name'=>'Tailoring customer','phone'=>'TC-'.uniqid()]);
        $order=TailorOrder::create(['customer_id'=>$customer->id,'total'=>'100','balance'=>'100','status'=>'Pending']);
        $delivery=Delivery::create(['order_id'=>$order->id,'status'=>'Pending']);
        $this->patchJson(route('delivery.status',$delivery),['status'=>'Delivered'])->assertUnprocessable();
        $this->assertSame('Pending',$delivery->fresh()->status);$this->assertSame('Pending',$order->fresh()->status);
    }
    public function test_reports_and_dashboard_render_after_partial_return(): void
    {
        [$c,$p,$o]=$this->sale();$r=$this->returned($o,'3.00');app(ReturnService::class)->transition($r->id,'Completed');
        $this->getJson(route('cloth-store.dashboard'))->assertOk()->assertJsonPath('kpis.sales',70);
        $this->get(route('cloth-store.reports.index'))->assertOk()->assertViewHas('kpis',fn($k)=>$k['net_sales']==70);
    }
    public function test_cancel_paid_and_unpaid_sales(): void
    {
        foreach (['0.00','100.00'] as $paid) {
            [$c,$p,$o]=$this->sale($paid);app(OrderWorkflow::class)->transition($o->id,'Cancelled');
            $this->assertSame('0.00',$c->fresh()->due_balance);$this->assertSame($paid,$o->fresh()->refund_due);
        }
    }
    public function test_reversal_after_refund_does_not_double_customer_credit(): void
    {
        [$c,$p,$o]=$this->sale();$payment=CustomerPayment::where('cs_order_id',$o->id)->firstOrFail();
        $r=$this->returned($o,'10.00');app(ReturnService::class)->transition($r->id,'Completed');
        app(FinanceService::class)->reverse($payment->id);
        $this->assertSame('100.00',$c->fresh()->due_balance); // cash refund already left the shop
        $this->assertSame('0.00',$o->fresh()->refund_due);
    }
    public function test_pos_filters_inactive_and_unavailable_products(): void
    {
        [$c,$p]=$this->sale();$p->update(['status'=>'Inactive']);
        $this->getJson(route('cloth-store.checkout.scan',['code'=>$p->sku]))->assertNotFound();
        $this->postJson(route('cloth-store.checkout.store'),['cs_customer_id'=>$c->id,'items'=>[['cs_product_id'=>$p->id,'quantity'=>1]],'paid_amount'=>10,'payment_method'=>'Cash'])->assertUnprocessable();
    }
    public function test_tax_and_service_charge_calculation(): void
    {
        Settings::put(['tax_enabled'=>true,'tax_inclusive'=>false,'tax_rate'=>'7.50','service_charge_enabled'=>true,'service_charge_rate'=>'2.50']);
        $this->assertSame('110.00',PricingService::grandTotal('100.00'));
        Settings::put(['tax_inclusive'=>true]);$this->assertSame('100.00',PricingService::grandTotal('100.00'));
    }
    public function test_gateway_failure_returns_existing_manual_fallback(): void
    {
        Settings::put(['whatsapp_enabled'=>true,'whatsapp_provider'=>'gateway','gateway_url'=>'http://127.0.0.1:3001','gateway_token'=>str_repeat('a',40)]);
        \Illuminate\Support\Facades\Http::fake(['*'=>\Illuminate\Support\Facades\Http::response(['error'=>'offline'],503)]);
        $result=\App\Services\WhatsAppService::send('03001234567','Test only');
        $this->assertFalse($result['sent']);
    }
    public function test_json_backup_redacts_secret_settings(): void
    {
        Settings::put(['gateway_token'=>str_repeat('s',40),'store_name'=>'Backup shop']);
        $settings=BackupService::payload()['settings'];
        $this->assertSame('[redacted]',$settings['gateway_token']);
        $this->assertSame('Backup shop',$settings['store_name']);
    }
    public function test_staff_payment_reversal_retains_linked_history(): void
    {
        $staff=Staff::create(['name'=>'Payroll test','salary_type'=>'Monthly','monthly_salary'=>'100.00']);
        $period=now()->format('Y-m');
        $this->postJson(route('staff.payments.store',$staff),[
            'amount'=>'40.00','method'=>'Cash','period'=>$period,'operation_key'=>'staff-pay-'.uniqid(),
        ])->assertSuccessful();
        $payment=StaffPayment::where('staff_id',$staff->id)->whereNull('reverses_payment_id')->firstOrFail();
        $this->deleteJson(route('staff.payments.destroy',$payment))->assertSuccessful();
        $this->assertNotNull($payment->fresh()->reversed_at);
        $this->assertDatabaseHas('staff_payments',['reverses_payment_id'=>$payment->id,'status'=>'Reversal']);
        $this->assertSame(100.0,$staff->fresh()->dueFor($period)['remaining']);
    }
}
