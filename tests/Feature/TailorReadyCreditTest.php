<?php
namespace Tests\Feature;

use App\Models\{Order, Staff, StaffWorkLog};
use App\Services\OrderService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};
use Tests\TestCase;

final class TailorReadyCreditTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Other isolated tests use smaller schemas; forget their cached columns.
        (new \ReflectionProperty(\Illuminate\Database\Eloquent\Model::class,'guardableColumns'))->setValue(null,[]);
        config(['database.default'=>'sqlite','database.connections.sqlite.database'=>':memory:','database.connections.sqlite.url'=>null]); DB::purge('sqlite');
        Schema::create('orders',function(Blueprint $t){
            $t->id(); $t->integer('staff_id')->nullable(); $t->string('status'); $t->integer('progress')->default(0);
            $t->integer('edit_version')->default(0); $t->json('items')->nullable(); $t->string('garment')->nullable();
            $t->integer('customer_id')->nullable(); $t->decimal('quantity')->nullable(); $t->decimal('unit_price')->nullable();
            $t->dateTime('items_migrated_at')->nullable();
            $t->dateTime('completed_at')->nullable(); $t->dateTime('delivered_at')->nullable(); $t->timestamps(); $t->softDeletes();
        });
        Schema::create('order_items',function(Blueprint $t){
            $t->id(); $t->integer('order_id'); $t->integer('product_service_id')->nullable(); $t->integer('position')->default(0);
            $t->string('name'); $t->decimal('quantity'); $t->decimal('unit_price')->nullable(); $t->decimal('tailor_rate_override')->nullable();
            $t->timestamps(); $t->softDeletes();
        });
        Schema::create('order_item_pieces',function(Blueprint $t){
            $t->id(); $t->integer('order_item_id'); $t->integer('piece_no')->default(1); $t->json('profile')->nullable();
            $t->timestamps(); $t->softDeletes();
        });
        Schema::create('staff',function(Blueprint $t){$t->id(); $t->string('name'); $t->decimal('per_suit_rate'); $t->timestamps();});
        Schema::create('staff_work_logs',function(Blueprint $t){
            $t->id(); $t->integer('staff_id'); $t->integer('order_id')->unique(); $t->string('garment');
            $t->decimal('quantity'); $t->decimal('rate'); $t->decimal('amount'); $t->date('completed_on'); $t->text('notes'); $t->timestamps();
        });
        Schema::create('staff_service_rates',function(Blueprint $t){
            $t->id(); $t->integer('staff_id'); $t->integer('product_service_id'); $t->decimal('rate'); $t->timestamps();
        });
    }

    public function test_ready_is_credited_on_completion_date_once_even_after_delivery_and_rate_change(): void
    {
        $staff=Staff::create(['name'=>'Test tailor','per_suit_rate'=>25]);
        $order=Order::create(['staff_id'=>$staff->id,'status'=>'Ready','completed_at'=>'2026-08-31 18:00:00',
            'items_migrated_at'=>now()]);
        \App\Models\OrderItem::create(['order_id'=>$order->id, 'name'=>'Shirt', 'quantity'=>3, 'unit_price'=>100, 'tailor_rate_override'=>30]);
        $service=app(OrderService::class);
        $this->assertTrue($service->reconcileStaffWork($order));
        $log=StaffWorkLog::firstOrFail();
        $this->assertSame('3.00',$log->quantity);
        $this->assertSame('90.00',$log->amount);
        $this->assertSame('2026-08-31',$log->completed_on->toDateString());
        $staff->update(['per_suit_rate'=>100]);
        $order->update(['status'=>'Delivered','delivered_at'=>'2026-09-11 18:00:00']);
        $this->assertFalse($service->reconcileStaffWork($order));
        $this->assertSame(1,StaffWorkLog::count());
        $this->assertSame('90.00',$log->fresh()->amount);
        $this->assertEquals(3,StaffWorkLog::whereMonth('completed_on',8)->sum('quantity'));
        $this->assertEquals(0,StaffWorkLog::whereMonth('completed_on',9)->sum('quantity'));
    }

    public function test_verification_and_unassigned_orders_do_not_credit_a_tailor(): void
    {
        $staff=Staff::create(['name'=>'Test tailor','per_suit_rate'=>25]);
        $order=Order::create(['staff_id'=>$staff->id,'status'=>'Ready for Verification','items'=>[['name'=>'Shirt','qty'=>2]]]);
        $this->assertFalse(app(OrderService::class)->reconcileStaffWork($order));
        $order->update(['status'=>'Ready','staff_id'=>null]);
        $this->assertFalse(app(OrderService::class)->reconcileStaffWork($order));
        $this->assertSame(0,StaffWorkLog::count());
    }
}
