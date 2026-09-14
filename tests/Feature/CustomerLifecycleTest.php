<?php

namespace Tests\Feature;

use App\Http\Controllers\CustomerController;
use App\Models\{Customer, Order, Payment};
use App\Services\{CustomerLifecycle, ReportAnalytics};
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};
use Tests\TestCase;

final class CustomerLifecycleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default'=>'sqlite','database.connections.sqlite.database'=>':memory:','database.connections.sqlite.url'=>null]);
        DB::purge('sqlite');
        $this->withoutMiddleware([\Illuminate\Auth\Middleware\Authenticate::class,
            \App\Http\Middleware\ApplyShopSettings::class,
            \App\Http\Middleware\EnsureUserIsActive::class, \App\Http\Middleware\AuthorizeBusinessRequest::class]);
        Schema::create('customers', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->string('phone'); $t->string('code')->nullable();
            foreach (['email','city','address','notes','behavior'] as $f) $t->text($f)->nullable();
            $t->string('type')->default('Regular'); $t->float('loyalty_score')->nullable();
            $t->timestamp('last_visit_at')->nullable(); $t->boolean('is_active')->default(true); $t->timestamps(); $t->softDeletes();
        });
        (require database_path('migrations/2026_09_12_000001_customer_archive_lifecycle.php'))->up();
        Schema::create('orders', function (Blueprint $t) {
            $t->id(); $t->foreignId('customer_id')->constrained()->restrictOnDelete();
            $t->string('order_number')->nullable(); $t->string('invoice_number')->nullable();
            $t->string('status')->default('Received'); $t->integer('progress')->default(0); $t->integer('edit_version')->default(0);
            $t->decimal('total',12,2)->default(0); $t->decimal('balance',12,2)->default(0); $t->decimal('advance',12,2)->default(0);
            $t->date('delivery_date')->nullable(); $t->text('notes')->nullable(); $t->text('style_notes')->nullable();
            $t->timestamps(); $t->softDeletes();
        });
        Schema::create('payments', function (Blueprint $t) {
            $t->id(); $t->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('order_id')->nullable()->constrained()->restrictOnDelete();
            $t->string('invoice_id')->nullable(); $t->decimal('amount',12,2); $t->string('status')->default('Paid');
            $t->text('notes')->nullable(); $t->timestamps();
        });
        Schema::create('measurements', function (Blueprint $t) {
            $t->id(); $t->foreignId('customer_id')->constrained()->restrictOnDelete();
            $t->text('notes')->nullable(); $t->string('template_name')->nullable(); $t->decimal('chest')->nullable();
        });
        Schema::create('deliveries', function (Blueprint $t) {
            $t->id(); $t->foreignId('order_id')->constrained();
            foreach (['recipient_name','address','notes'] as $f) $t->text($f)->nullable();
        });
        Schema::create('notifications', function (Blueprint $t) {
            $t->id(); $t->foreignId('customer_id')->nullable()->constrained()->cascadeOnDelete(); $t->integer('order_id')->nullable();
            $t->string('title'); $t->text('message');
        });
        foreach (['sms_logs','whats_app_logs'] as $table) Schema::create($table, function (Blueprint $t) {
            $t->id(); $t->integer('customer_id')->nullable(); $t->integer('order_id')->nullable();
            $t->string('phone'); $t->text('message'); $t->text('error')->nullable(); $t->text('api_response')->nullable();
            $t->string('status')->default('sent');
        });
        Schema::create('activity_logs', function (Blueprint $t) {
            $t->id(); foreach (['action','category','event','description','subject_type','properties','actor_name','ip_address'] as $f) $t->text($f)->nullable();
            $t->integer('subject_id')->nullable(); $t->integer('user_id')->nullable(); $t->timestamps();
        });
        Schema::create('integrity_audits', function (Blueprint $t) {
            $t->id(); $t->string('source_table'); $t->integer('source_id'); $t->text('evidence');
        });
    }

    private function customer(): Customer
    {
        return Customer::create(['name'=>'Private Person','phone'=>'03001234567','email'=>'private@example.test',
            'address'=>'Private address','city'=>'Karachi','notes'=>'Private details']);
    }

    protected function tearDown(): void
    {
        // This suite owns a minimal in-memory schema; do not leak Eloquent's
        // per-model column cache into suites which create a different schema.
        (new \ReflectionProperty(\Illuminate\Database\Eloquent\Model::class, 'guardableColumns'))->setValue(null, []);
        parent::tearDown();
    }

    public function test_archive_hides_customer_and_restore_returns_same_record(): void
    {
        $c = $this->customer();
        $this->deleteJson('/customers/'.$c->id)->assertOk();
        $this->assertNull(Customer::find($c->id));
        $this->assertSame(1, Customer::onlyTrashed()->count());
        $view = app(CustomerController::class)->index()->getData();
        $this->assertCount(0,$view['customers']); $this->assertCount(1,$view['archivedCustomers']);
        $this->postJson('/customers/'.$c->id.'/restore')->assertOk()->assertJsonPath('customer.db_id',$c->id);
        $this->assertSame($c->phone,Customer::findOrFail($c->id)->phone);
        $this->assertSame(1,Customer::withTrashed()->count());
    }

    public function test_archived_phone_detected_in_all_common_formats_without_duplicate(): void
    {
        $c = $this->customer(); $c->delete();
        foreach (['03001234567','0300-1234567','+92 300 1234567','00923001234567'] as $phone) {
            $this->postJson('/customers',['name'=>'Another name','phone'=>$phone])->assertStatus(409)
                ->assertJsonPath('archived_customer.db_id',$c->id)->assertJsonStructure(['errors'=>['phone']]);
        }
        $this->assertSame(1,Customer::withTrashed()->count());
    }

    public function test_active_phone_duplicate_and_edit_collision_are_rejected(): void
    {
        $c = $this->customer();
        $this->postJson('/customers',['name'=>'Duplicate','phone'=>'+923001234567'])->assertUnprocessable();
        $other = Customer::create(['name'=>'Other','phone'=>'03001111111']); $c->delete();
        $this->putJson('/customers/'.$other->id,['name'=>'Other','phone'=>$c->phone])->assertStatus(409);
        $this->assertSame('03001111111',$other->fresh()->phone);
    }

    public function test_permanent_delete_requires_exact_confirmation_and_archive_first(): void
    {
        $c=$this->customer();
        $this->deleteJson('/customers/'.$c->id.'/permanent',['confirmation'=>'DELETE'])->assertNotFound();
        $c->delete();
        foreach (['','delete','CONFIRM'] as $confirmation) {
            $this->deleteJson('/customers/'.$c->id.'/permanent',['confirmation'=>$confirmation])->assertUnprocessable();
        }
        $this->assertNotNull(Customer::withTrashed()->find($c->id));
    }

    public function test_no_business_history_is_hard_deleted_even_with_directory_audit_events(): void
    {
        $c=$this->customer();
        $this->deleteJson('/customers/'.$c->id)->assertOk();
        $this->deleteJson('/customers/'.$c->id.'/permanent',['confirmation'=>'DELETE'])->assertOk()->assertJsonPath('result','deleted');
        $this->assertNull(Customer::withTrashed()->find($c->id));
        $this->assertSame(2,DB::table('activity_logs')->count());
    }

    public function test_history_is_anonymized_and_orders_payments_invoices_reports_preserved(): void
    {
        $c=$this->customer();
        $order=Order::create(['customer_id'=>$c->id,'total'=>1000,'advance'=>300,'balance'=>700,
            'order_number'=>'ORD-KEEP','invoice_number'=>'INV-KEEP','notes'=>'Private details','delivery_date'=>now()->addDay()]);
        $payment=Payment::create(['customer_id'=>$c->id,'order_id'=>$order->id,'amount'=>300,'invoice_id'=>'INV-KEEP','notes'=>'Private details']);
        DB::table('deliveries')->insert(['order_id'=>$order->id,'recipient_name'=>$c->name,'address'=>$c->address]);
        DB::table('notifications')->insert(['customer_id'=>$c->id,'order_id'=>$order->id,'title'=>$c->name,'message'=>$c->phone]);
        DB::table('sms_logs')->insert(['customer_id'=>$c->id,'phone'=>$c->phone,'message'=>$c->name]);
        $auditId=DB::table('activity_logs')->insertGetId(['subject_type'=>Payment::class,'subject_id'=>$payment->id,
            'description'=>$c->name.' paid 300','properties'=>json_encode(['amount'=>300,'invoice_id'=>'INV-KEEP','customer'=>['name'=>$c->name,'phone'=>$c->phone]])]);
        $before=ReportAnalytics::dues();
        $this->deleteJson('/customers/'.$c->id)->assertOk();
        $this->assertSame($c->name,$order->fresh()->customer->name);
        $this->deleteJson('/customers/'.$c->id.'/permanent',['confirmation'=>'DELETE'])->assertOk()->assertJsonPath('result','anonymized');
        $anonymous=Customer::withTrashed()->findOrFail($c->id);
        $this->assertNotNull($anonymous->anonymized_at); $this->assertTrue($anonymous->trashed());
        foreach (['email','address','city','notes','phone_key'] as $field) $this->assertNull($anonymous->$field);
        $this->assertSame('Removed',$anonymous->phone);
        $this->assertSame('INV-KEEP',$order->fresh()->invoice_number);
        $this->assertSame('1000.00',$order->fresh()->total); $this->assertSame('700.00',$order->fresh()->balance);
        $this->assertSame('300.00',$payment->fresh()->amount); $this->assertEquals($c->id,$payment->fresh()->customer_id);
        $this->assertSame($anonymous->name,$order->fresh()->customer->name);
        $this->assertSame($anonymous->name,$payment->fresh()->customer->name);
        $after=ReportAnalytics::dues(); $this->assertSame($before['total'],$after['total']); $this->assertSame($before['orders'],$after['orders']);
        $this->assertSame($anonymous->name,$after['customers'][0]['name']);
        $this->assertNull(DB::table('deliveries')->value('recipient_name'));
        $this->assertSame('Removed',DB::table('sms_logs')->value('phone'));
        $this->assertSame(1,DB::table('notifications')->count());
        $audit=DB::table('activity_logs')->find($auditId);
        $this->assertSame('[removed] paid 300',$audit->description);
        $this->assertSame(['amount'=>300,'invoice_id'=>'INV-KEEP','customer'=>['name'=>null,'phone'=>null]],json_decode($audit->properties,true));
        $this->assertSame(0,Customer::onlyTrashed()->whereNull('anonymized_at')->count());
        $this->postJson('/customers/'.$c->id.'/restore')->assertNotFound();
        $this->postJson('/customers',['name'=>'New owner of phone','phone'=>$c->phone])->assertCreated();
    }

    public function test_archived_orders_and_standalone_payments_also_prevent_hard_delete(): void
    {
        $c=$this->customer();
        $order=Order::create(['customer_id'=>$c->id,'total'=>200]); $order->delete(); $c->delete();
        $this->assertSame('anonymized',app(CustomerLifecycle::class)->permanentlyDelete($c->id,'DELETE'));
        $this->assertNotNull(Order::withTrashed()->find($order->id));
        $other=Customer::create(['name'=>'Payment only','phone'=>'03009999999']);
        $p=Payment::create(['customer_id'=>$other->id,'amount'=>20]); $other->delete();
        $this->assertSame('anonymized',app(CustomerLifecycle::class)->permanentlyDelete($other->id,'DELETE'));
        $this->assertSame('20.00',$p->fresh()->amount);
    }

    public function test_measurement_only_history_is_retained(): void
    {
        $c=$this->customer();
        DB::table('measurements')->insert(['customer_id'=>$c->id,'chest'=>40,'notes'=>$c->name]); $c->delete();
        $this->assertSame('anonymized',app(CustomerLifecycle::class)->permanentlyDelete($c->id,'DELETE'));
        $this->assertEquals(40,DB::table('measurements')->value('chest'));
        $this->assertNull(DB::table('measurements')->value('notes'));
    }

    public function test_legacy_normalized_conflict_cannot_be_restored_over_active_customer(): void
    {
        $c=$this->customer();
        $id=DB::table('customers')->insertGetId(['name'=>'Legacy duplicate','phone'=>'+923001234567','deleted_at'=>now()]);
        $this->postJson('/customers/'.$id.'/restore')->assertUnprocessable();
        $this->assertNotNull(Customer::onlyTrashed()->find($id));
        $this->assertSame($c->id,CustomerLifecycle::matchingPhone($c->phone)->id);
        $this->putJson('/customers/'.$c->id,['name'=>'Updated active customer','phone'=>$c->phone])->assertOk();
        $this->assertSame('Updated active customer',$c->fresh()->name);
    }

    public function test_import_skips_archived_customer_instead_of_creating_duplicate(): void
    {
        $c=$this->customer(); $c->delete();
        $file=tmpfile(); $path=stream_get_meta_data($file)['uri'];
        fwrite($file,"name,phone\nAnother name,+923001234567\n"); fflush($file);
        try {
            $result=\App\Services\CustomerImporter::process(['cache'=>$path,'mapping'=>['name'=>0,'phone'=>1],'options'=>[]],0,10,false);
            $this->assertSame(1,$result['skipped']); $this->assertSame(0,$result['created']);
            $this->assertStringContainsString('Restore Customer',$result['issues'][0]['message']);
            $this->assertSame(1,Customer::withTrashed()->count());
        } finally { fclose($file); }
    }

    public function test_direct_model_creation_cannot_bypass_archived_phone_check(): void
    {
        $c=$this->customer(); $c->delete();
        try {
            Customer::create(['name'=>'Duplicate','phone'=>'+923001234567']);
            $this->fail('Duplicate was created.');
        } catch (\Illuminate\Http\Exceptions\HttpResponseException $e) {
            $this->assertSame(409,$e->getResponse()->getStatusCode());
        }
        $this->assertSame(1,Customer::withTrashed()->count());
    }

    public function test_scrub_failure_rolls_back_personal_and_financial_records(): void
    {
        $c=$this->customer(); $c->delete();
        $order=Order::create(['customer_id'=>$c->id,'total'=>1000,'notes'=>'Keep until operation succeeds']);
        DB::table('notifications')->insert(['customer_id'=>$c->id,'title'=>'Original','message'=>'Original']);
        DB::unprepared("CREATE TRIGGER reject_scrub BEFORE UPDATE ON notifications BEGIN SELECT RAISE(ABORT, 'Test failure'); END");
        try {
            app(CustomerLifecycle::class)->permanentlyDelete($c->id,'DELETE');
            $this->fail('Expected transaction failure.');
        } catch (\Illuminate\Database\QueryException $e) {
            $this->assertStringContainsString('Test failure',$e->getMessage());
        }
        $this->assertSame($c->name,$c->fresh()->name); $this->assertNull($c->fresh()->anonymized_at);
        $this->assertSame('Keep until operation succeeds',$order->fresh()->notes);
        $this->assertSame('1000.00',$order->fresh()->total);
    }

    public function test_restore_and_permanent_delete_keep_owner_authorization(): void
    {
        $c=$this->customer(); $c->delete();
        $this->withMiddleware(\App\Http\Middleware\AuthorizeBusinessRequest::class);
        $this->postJson('/customers/'.$c->id.'/restore')->assertForbidden();
        $this->deleteJson('/customers/'.$c->id.'/permanent',['confirmation'=>'DELETE'])->assertForbidden();
        $this->assertNotNull(Customer::onlyTrashed()->find($c->id));
    }
}
