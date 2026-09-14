<?php
namespace Tests\Feature;

use App\Models\{Customer,Order,SmsLog};
use App\Services\{CollectionNotifications,CollectionBoard,Settings,OrderService,DeliveryAttentionService};
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB,Schema,Http};
use Tests\TestCase;

final class CollectionWorkflowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default'=>'sqlite','database.connections.sqlite.database'=>':memory:','database.connections.sqlite.url'=>null]); DB::purge('sqlite');
        $this->withoutMiddleware([\Illuminate\Auth\Middleware\Authenticate::class,\App\Http\Middleware\EnsureUserIsActive::class,
            \App\Http\Middleware\EnsureUserHasRole::class,
            \App\Http\Middleware\AuthorizeBusinessRequest::class,\App\Http\Middleware\ApplyShopSettings::class]);
        Schema::create('customers',function(Blueprint $t){$t->id();$t->string('name');$t->string('phone');$t->string('phone_key')->nullable()->unique();$t->string('code')->nullable();$t->timestamp('anonymized_at')->nullable();$t->timestamps();$t->softDeletes();});
        Schema::create('orders',function(Blueprint $t){
            $t->id();$t->foreignId('customer_id')->constrained();$t->string('status');$t->integer('progress')->default(0);$t->integer('edit_version')->default(0);
            $t->string('time_slot')->nullable();$t->string('order_number')->nullable();$t->string('garment')->nullable();$t->json('items')->nullable();$t->integer('staff_id')->nullable();
            $t->decimal('total',12,2)->default(100);$t->decimal('advance',12,2)->default(0);$t->decimal('balance',12,2)->default(100);
            foreach(['delivery_date','completed_at','delivered_at','notified_at','ready_sms_attempted_at','items_migrated_at'] as $f)$t->timestamp($f)->nullable();
            $t->string('ready_sms_state')->nullable();$t->uuid('ready_sms_attempt_id')->nullable();$t->timestamps();$t->softDeletes();
        });
        Schema::create('order_items',function(Blueprint $t){$t->id();$t->integer('order_id');$t->integer('product_service_id')->nullable();$t->integer('position')->default(0);$t->integer('quantity')->default(1);$t->string('name');$t->softDeletes();});
        Schema::create('payments',function(Blueprint $t){$t->id();$t->integer('order_id');$t->integer('customer_id')->nullable();$t->string('type')->nullable();$t->decimal('amount',12,2);$t->string('status');$t->integer('reverses_payment_id')->nullable();$t->timestamp('date')->nullable();});
        Schema::create('order_status_histories',function(Blueprint $t){$t->id();$t->integer('order_id');$t->string('from_status')->nullable();$t->string('to_status');$t->string('label')->nullable();$t->text('note')->nullable();$t->integer('user_id')->nullable();$t->string('actor_name')->nullable();$t->timestamps();});
        Schema::create('deliveries',function(Blueprint $t){$t->id();$t->integer('order_id')->unique();$t->string('status');$t->text('address')->nullable();$t->text('recipient_name')->nullable();$t->timestamp('delivery_date')->nullable();$t->timestamp('delivered_at')->nullable();$t->timestamps();});
        Schema::create('notifications',function(Blueprint $t){$t->id();foreach(['title','message','type','category','icon','color','action_url'] as $f)$t->text($f)->nullable();$t->string('event_key')->nullable()->unique();$t->integer('customer_id')->nullable();$t->integer('order_id')->nullable();$t->boolean('is_read')->default(false);$t->timestamps();});
        Schema::create('activity_logs',function(Blueprint $t){$t->id();foreach(['action','category','event','description','subject_type','properties','actor_name','ip_address'] as $f)$t->text($f)->nullable();$t->integer('subject_id')->nullable();$t->integer('user_id')->nullable();$t->timestamps();});
        (require database_path('migrations/2026_08_07_233018_create_settings_table.php'))->up();
        (require database_path('migrations/2026_08_25_135245_create_sms_logs_table.php'))->up();
        Schema::table('sms_logs',fn(Blueprint $t)=>$t->string('provider_message_id')->nullable());
        (require database_path('migrations/2026_09_12_000002_collection_sms_workflow.php'))->up();
        (require database_path('migrations/2026_09_14_000001_add_tailor_customer_ledger.php'))->up();
        Settings::flush();
        Settings::put(['sms_enabled'=>true,'sms_provider'=>'veevo','veevo_api_key'=>'fixture-key','collection_reminder_days'=>7]);
        config(['app.timezone'=>'Asia/Karachi']);date_default_timezone_set('Asia/Karachi');
        $this->travelTo(now()->startOfDay()->setTime(15,0));
        $this->fakeHttp(['api.veevotech.com/*'=>Http::response(['STATUS'=>'SUCCESSFUL','MESSAGE_ID'=>'fixture-message'])]);
    }
    protected function tearDown(): void
    {
        $this->travelBack();Settings::flush();
        (new \ReflectionProperty(\Illuminate\Database\Eloquent\Model::class,'guardableColumns'))->setValue(null,[]);
        parent::tearDown();
    }
    private function order(?Customer $customer=null,string $status='Ready for Verification'): Order
    {
        $customer ??= Customer::create(['name'=>'Test customer','phone'=>'0300'.str_pad((string)(Customer::withTrashed()->count()+1),7,'0',STR_PAD_LEFT)]);
        return Order::create(['customer_id'=>$customer->id,'order_number'=>'ORD-'.(Order::count()+1),'status'=>$status,'delivery_date'=>now()->addDay(),
            'items'=>[['name'=>'Shirt','qty'=>2,'price'=>50]],'garment'=>'Shirt']);
    }
    private function fakeHttp(array $responses): void { Http::swap(new \Illuminate\Http\Client\Factory); Http::preventStrayRequests(); Http::fake($responses); }
    private function send(array $ids): array {return app(CollectionNotifications::class)->sendOrders($ids);}

    public function test_success_changes_status_only_after_provider_acceptance_and_preserves_totals(): void
    {
        $o=$this->order();
        $this->fakeHttp(['api.veevotech.com/*'=>function()use($o){$this->assertSame('Ready for Verification',$o->fresh()->status);return Http::response(['STATUS'=>'SUCCESSFUL','MESSAGE_ID'=>'ok']);}]);
        $result=$this->send([$o->id]);
        $this->assertSame(1,$result['sent']);$this->assertSame('Ready',$o->fresh()->status);
        $this->assertSame('100.00',$o->fresh()->total);$this->assertSame('100.00',$o->fresh()->balance);
        $this->assertSame(1,SmsLog::count());$this->assertSame('collection-first',SmsLog::first()->reason);
        $this->assertSame(1,DB::table('order_status_histories')->where('to_status','Ready')->count());
        $this->assertSame(0,$this->send([$o->id])['sent']);Http::assertSentCount(1);
    }
    public function test_same_customer_selected_orders_share_one_sms_and_all_links(): void
    {
        $a=$this->order();$b=$this->order($a->customer);
        $result=$this->send([$a->id,$b->id,$a->id]);
        $this->assertSame(1,$result['sent']);$this->assertSame(2,$result['promoted']);Http::assertSentCount(1);
        $this->assertSame(2,DB::table('collection_sms_orders')->count());
        Http::assertSent(fn($r)=>str_contains($r['textmessage'],$a->order_number) && str_contains($r['textmessage'],$b->order_number));
        $this->assertSame(1,app(CollectionNotifications::class)->state($b->fresh())['smsCount']);
    }
    public function test_provider_failure_is_logged_and_retry_succeeds_without_early_ready(): void
    {
        $o=$this->order();$this->fakeHttp(['api.veevotech.com/*'=>Http::response(['STATUS'=>'FAILED','ERROR_DESCRIPTION'=>'No credit'])]);
        $result=$this->send([$o->id]);$this->assertCount(1,$result['failed']);$this->assertSame('Ready for Verification',$o->fresh()->status);
        $this->assertNull($o->fresh()->notified_at);$this->assertSame('failed',SmsLog::first()->status);
        $this->fakeHttp(['api.veevotech.com/*'=>Http::response(['STATUS'=>'SUCCESSFUL','MESSAGE_ID'=>'retry'])]);
        $this->assertSame(1,$this->send([$o->id])['sent']);$this->assertSame(2,SmsLog::count());
    }
    public function test_seven_day_boundary_and_each_reminder_restarts_cooldown(): void
    {
        $o=$this->order();$this->send([$o->id]);$first=$o->fresh()->notified_at;
        $this->travelTo($first->copy()->addDays(7)->subSecond());
        $this->assertSame(0,$this->send([$o->id])['sent']);
        $this->travelTo($first->copy()->addDays(7));
        $this->assertTrue(app(CollectionNotifications::class)->state($o->fresh())['reminderDue']);
        $this->assertSame(1,$this->send([$o->id])['sent']);$this->assertSame('collection-reminder',SmsLog::latest('id')->first()->reason);
        $this->assertEquals($first,$o->fresh()->notified_at);
        $this->travelTo($first->copy()->addDays(13));$this->assertSame(0,$this->send([$o->id])['sent']);
        $this->travelTo($first->copy()->addDays(14));$this->assertSame(1,$this->send([$o->id])['sent']);
        $this->assertSame(3,app(CollectionNotifications::class)->state($o->fresh())['smsCount']);
    }
    public function test_disabled_sms_records_failure_without_transition(): void
    {
        Settings::put(['sms_enabled'=>false]);$o=$this->order();
        $this->assertCount(1,$this->send([$o->id])['failed']);Http::assertNothingSent();
        $this->assertSame('failed',SmsLog::first()->status);$this->assertSame('Ready for Verification',$o->fresh()->status);
    }
    public function test_unready_collected_archived_and_invalid_phone_never_send(): void
    {
        $a=$this->order(status:'Stitching');$b=$this->order(status:'Delivered');$c=$this->order();$c->delete();
        $d=$this->order();$d->customer->update(['phone'=>'not a phone']);
        $r=$this->send([$a->id,$b->id,$c->id,$d->id]);$this->assertSame(0,$r['sent']);$this->assertCount(4,$r['skipped']);Http::assertNothingSent();
    }
    public function test_pending_attempt_blocks_other_order_for_same_customer(): void
    {
        $a=$this->order();$b=$this->order($a->customer);
        SmsLog::create(['customer_id'=>$a->customer_id,'phone'=>\App\Services\NotificationPhone::normalize($a->customer->phone),'message'=>'Pending','reason'=>'collection-first','status'=>'sending']);
        $this->assertSame(0,$this->send([$a->id,$b->id])['sent']);Http::assertNothingSent();
    }
    public function test_settings_validation_persistence_and_custom_interval(): void
    {
        $this->putJson('/settings',['collection_reminder_days'=>0])->assertUnprocessable();
        $this->putJson('/settings',['collection_reminder_days'=>3,'delivery_alert_before_days'=>5,'delivery_alerts_enabled'=>false])->assertOk();
        Settings::flush();$this->assertSame(3,Settings::int('collection_reminder_days'));$this->assertFalse(Settings::bool('delivery_alerts_enabled'));
        $o=$this->order();$this->send([$o->id]);$this->travel(3)->days();$this->assertSame(1,$this->send([$o->id])['sent']);
    }
    public function test_ready_legacy_order_without_sms_can_receive_first_notice(): void
    {
        $o=$this->order(status:'Ready');$this->assertSame(1,$this->send([$o->id])['sent']);
        $this->assertSame('collection-first',SmsLog::first()->reason);
    }
    public function test_board_dates_and_unique_customer_counts_are_dynamic(): void
    {
        $a=$this->order();$a->update(['delivery_date'=>now()->addHour()]);
        $b=$this->order($a->customer);$b->update(['delivery_date'=>now()->addDays(2)]);
        $closed=$this->order(status:'Delivered');
        $data=app(CollectionBoard::class)->data();$this->assertSame(1,$data['stats']['total']);$this->assertSame(1,$data['stats']['dueToday']);
        $this->assertCount(3,$data['deliveries']);$this->assertSame(2,$data['deliveries'][0]['pieces']);
        $this->getJson('/delivery?json=1')->assertOk()->assertJsonPath('stats.needsNotification',1);
        $this->travel(2)->days();$data=app(CollectionBoard::class)->data();$this->assertSame(1,$data['stats']['overdue']);
    }
    public function test_collected_order_is_removed_and_never_gets_reminder(): void
    {
        $o=$this->order();$this->send([$o->id]);
        $this->postJson('/delivery/orders/'.$o->id.'/collect')->assertOk();
        $this->travel(8)->days();$this->assertSame(0,$this->send([$o->id])['sent']);
        $this->assertCount(1,app(CollectionBoard::class)->data()['deliveries']);$this->assertSame(0,app(CollectionBoard::class)->data()['stats']['overdue']);$this->assertSame(1,SmsLog::count());
    }
    public function test_http_validates_bulk_selection_and_lists_history(): void
    {
        $o=$this->order();$this->postJson('/delivery/bulk-notify',['order_ids'=>[$o->id,$o->id]])->assertUnprocessable();
        $this->postJson('/delivery/bulk-notify',['order_ids'=>[$o->id]])->assertOk()->assertJsonPath('sent',1);
        $this->getJson('/delivery/sms-history?order_id='.$o->id)->assertOk()->assertJsonPath('total',1)->assertJsonPath('data.0.status','accepted');
    }

    public function test_uncertain_provider_result_blocks_retry_until_operator_checks(): void
    {
        $o=$this->order();
        $this->fakeHttp(['api.veevotech.com/*'=>fn()=>throw new \Illuminate\Http\Client\ConnectionException('Timeout')]);
        $this->assertCount(1,$this->send([$o->id])['failed']);
        $log=SmsLog::first();$this->assertSame('unknown',$log->status);
        $this->assertSame(0,$this->send([$o->id])['sent']);
        $this->postJson('/delivery/sms/'.$log->id.'/resolve',['outcome'=>'accepted','confirmation'=>'CHECKED','provider_reference'=>'checked-123'])->assertUnprocessable();
        $this->travel(3)->minutes();
        $this->postJson('/delivery/sms/'.$log->id.'/resolve',['outcome'=>'accepted','confirmation'=>'CHECKED','provider_reference'=>'checked-123'])->assertOk();
        $this->assertSame('Ready',$o->fresh()->status);$this->assertSame(1,SmsLog::count());
        $this->assertSame(0,$this->send([$o->id])['sent']);
    }

    public function test_successful_reminder_clears_failure_and_preserves_payment_history(): void
    {
        $o=$this->order();DB::table('payments')->insert(['order_id'=>$o->id,'amount'=>35,'status'=>'Paid']);
        $this->send([$o->id]);$this->travel(7)->days();
        $this->fakeHttp(['api.veevotech.com/*'=>Http::response(['STATUS'=>'FAILED'])]);
        $this->assertCount(1,$this->send([$o->id])['failed']);$this->assertSame('Ready',$o->fresh()->status);
        $this->fakeHttp(['api.veevotech.com/*'=>Http::response(['STATUS'=>'SUCCESSFUL','MESSAGE_ID'=>'reminder-ok'])]);
        $this->assertSame(1,$this->send([$o->id])['sent']);$this->assertSame('sent',$o->fresh()->ready_sms_state);
        $this->assertEquals(35,DB::table('payments')->sum('amount'));$this->assertSame(1,DB::table('payments')->count());
        $this->assertSame(1,DB::table('order_status_histories')->where('to_status','Ready')->count());
    }

    public function test_sms_is_never_sent_inside_uncommitted_transaction(): void
    {
        $o=$this->order();DB::beginTransaction();
        try {$this->send([$o->id]);$this->fail('Must reject uncommitted SMS');}
        catch (\Illuminate\Validation\ValidationException $e) {$this->assertArrayHasKey('sms',$e->errors());}
        finally {DB::rollBack();}
        Http::assertNothingSent();$this->assertSame(0,SmsLog::count());
    }

    public function test_reminder_sms_setting_blocks_only_reminders(): void
    {
        Settings::put(['collection_reminder_sms_enabled'=>false]);$o=$this->order();
        $this->assertSame(1,$this->send([$o->id])['sent']);$this->travel(7)->days();
        $this->assertTrue(app(CollectionNotifications::class)->state($o->fresh())['reminderDue']);
        $this->assertSame(0,$this->send([$o->id])['sent']);Http::assertSentCount(1);
    }

    public function test_alerts_are_actionable_deduplicated_and_respect_settings(): void
    {
        Settings::put(['business_hours'=>[now()->format('l')=>['open'=>true,'from'=>'00:00','to'=>'23:59']]]);
        $o=$this->order();$o->update(['delivery_date'=>now()->addHour()]);
        $service=app(DeliveryAttentionService::class);$this->assertGreaterThan(0,$service->run()['alerts']);
        $this->assertSame(0,$service->run()['alerts']);
        $this->assertTrue(DB::table('notifications')->where('action_url','like','%delivery%filter=%')->exists());
        $this->assertSame('Ready for Verification',$o->fresh()->status);Http::assertNothingSent();
        Settings::put(['delivery_alerts_enabled'=>false]);$this->travel(1)->days();
        $this->assertSame(0,$service->run()['alerts']);
    }

    public function test_premature_collection_is_rejected_by_every_status_endpoint(): void
    {
        foreach (['Received','Pending','Stitching','In Progress','Ready for Verification'] as $status) {
            $o=$this->order(status:$status);
            $delivery=DB::table('deliveries')->insertGetId(['order_id'=>$o->id,'status'=>'Scheduled']);
            $this->postJson('/delivery/orders/'.$o->id.'/collect')->assertUnprocessable();
            $this->patchJson('/delivery/'.$delivery.'/status',['status'=>'Delivered'])->assertUnprocessable();
            $this->patchJson('/orders/'.$o->id.'/status',['status'=>'Delivered'])->assertUnprocessable();
            try {app(OrderService::class)->update($o,['status'=>'Delivered']);$this->fail('Premature collection accepted');}
            catch (\Illuminate\Validation\ValidationException $e) {$this->assertArrayHasKey('status',$e->errors());}
            $this->assertSame($status,$o->fresh()->status);$this->assertNull($o->fresh()->delivered_at);
        }
        Http::assertNothingSent();
    }

    public function test_confirmed_flag_cannot_bypass_successful_sms_evidence(): void
    {
        $o=$this->order();
        try {app(OrderService::class)->changeStatus($o,'Ready',null,true);$this->fail('Missing SMS accepted');}
        catch (\Illuminate\Validation\ValidationException $e) {$this->assertArrayHasKey('status',$e->errors());}
        $this->assertSame('Ready for Verification',$o->fresh()->status);
    }

    public function test_reminder_master_switch_and_date_filters_follow_settings(): void
    {
        $o=$this->order();$this->send([$o->id]);$this->travel(6)->days();
        $this->assertFalse(app(CollectionNotifications::class)->state($o->fresh())['reminderDue']);
        $this->travel(1)->days();
        $this->putJson('/settings',['collection_reminder_enabled'=>false])->assertOk();
        $this->assertFalse(app(CollectionNotifications::class)->state($o->fresh())['reminderDue']);
        $this->assertSame(0,$this->send([$o->id])['sent']);
        Settings::put(['collection_reminder_enabled'=>true,'delivery_alert_before_days'=>2]);
        $this->assertTrue(app(CollectionNotifications::class)->state($o->fresh())['reminderDue']);
        $o->update(['delivery_date'=>now()->startOfDay()]);
        $row=app(CollectionBoard::class)->data()['deliveries']->first();
        $this->assertTrue($row['dueToday']);$this->assertFalse($row['overdue']);
        $o->update(['delivery_date'=>now()->addDays(2)]);
        $this->assertTrue(app(CollectionBoard::class)->data()['deliveries']->first()['upcoming']);
        Settings::put(['delivery_alert_before_days'=>1]);
        $this->assertFalse(app(CollectionBoard::class)->data()['deliveries']->first()['upcoming']);
    }

    public function test_repair_retains_history_and_collected_orders(): void
    {
        $unnotified=$this->order(status:'Ready');$notified=$this->order();$this->send([$notified->id]);
        $collected=$this->order(status:'Delivered');
        (require database_path('migrations/2026_09_12_000003_require_collection_notification_for_ready.php'))->up();
        $this->assertSame('Ready for Verification',$unnotified->fresh()->status);
        $this->assertSame('Ready',$notified->fresh()->status);$this->assertSame('Delivered',$collected->fresh()->status);
        $this->assertSame(1,$unnotified->statusHistories()->count());$this->assertSame(3,Order::count());
    }

    public function test_client_templates_resolve_balance_reminder_and_reschedule_context(): void
    {
        Settings::put(['store_name'=>'BEST TAILOR','phone'=>'03123456789']);
        $o=$this->order();
        $first=$this->send([$o->id]);
        $this->assertSame(1,$first['sent']);
        $text=SmsLog::latest('id')->first()->message;
        $this->assertStringContainsString('good news! Your order '.$o->display_number.' is now ready for collection.', $text);
        $this->assertStringContainsString('Balance due: Rs 100.', $text);
        $this->assertStringNotContainsString('Rs Rs',$text);
        $this->assertStringContainsString('contact us at 03123456789.', $text);
        $this->travel(7)->days();
        $this->assertSame(1,$this->send([$o->id])['sent']);
        $this->assertStringContainsString('this is a friendly reminder',SmsLog::latest('id')->first()->message);
        foreach (['Fabric delayed',''] as $reason) {
            $result=\App\Services\SmsService::sendTemplate('due-extended',$o,['oldDate'=>'14/09/2026','newDate'=>'20/09/2026','reason'=>$reason]);
            $this->assertTrue($result['sent']);
            $message=SmsLog::latest('id')->first()->message;
            $this->assertStringContainsString('rescheduled from 14/09/2026 to 20/09/2026.', $message);
            $this->assertStringContainsString('We sincerely apologize for the inconvenience and appreciate your patience.', $message);
            $this->assertStringNotContainsString('{',$message);
            if ($reason) $this->assertStringContainsString('Reason: Fabric delayed.', $message);
            else $this->assertStringNotContainsString('Reason:', $message);
        }
        $zero=$this->order();
        DB::table('payments')->insert(['order_id'=>$zero->id,'amount'=>100,'status'=>'Completed']);
        $this->assertSame(1,$this->send([$zero->id])['sent']);
        $this->assertStringContainsString('Balance due: Rs 0.',SmsLog::latest('id')->first()->message);
    }

    public function test_missing_phone_and_unknown_attempt_explain_skips_without_sending(): void
    {
        $o=$this->order();$o->customer->update(['phone'=>'']);
        $result=$this->send([$o->id]);
        $this->assertSame('Missing customer phone.',$result['skipped'][0]['reason']);
        $this->assertStringContainsString('Missing customer phone.',$result['message']);
        Http::assertNothingSent();
        $o=$this->order();$o->update(['order_number'=>'ORD-1056']);
        $this->fakeHttp(['api.veevotech.com/*'=>fn()=>throw new \Illuminate\Http\Client\ConnectionException('Timeout')]);
        $this->send([$o->id]);
        $result=$this->send([$o->id]);
        $this->assertSame(0,$result['sent']);$this->assertSame([],$result['failed']);$this->assertCount(1,$result['skipped']);
        $this->assertStringContainsString('ORD-1056: An SMS is sending or its result is unknown.', $result['message']);
        Http::assertSentCount(0);
        $this->assertSame(1,SmsLog::count());
    }

    public function test_invalid_template_context_is_logged_and_not_sent(): void
    {
        $o=$this->order();
        $result=\App\Services\SmsService::sendTemplate('due-extended',$o,['newDate'=>'20/09/2026']);
        $this->assertFalse($result['sent']);
        $this->assertStringContainsString('Missing reschedule date',$result['error']);
        $this->assertSame('failed',SmsLog::first()->status);
        $templates=Settings::defaultSmsTemplates();
        foreach($templates as &$t)if($t['id']==='order-ready')$t['text']='Order {orderID} {unsupported_value}';
        unset($t);Settings::put(['sms_templates'=>$templates]);
        $result=$this->send([$o->id]);
        $this->assertStringContainsString('Unsupported SMS placeholder',$result['failed'][0]['reason']);
        Http::assertNothingSent();
    }

    public function test_confirmed_connect_failure_is_retryable_but_does_not_promote_order(): void
    {
        $o=$this->order();
        $this->fakeHttp(['api.veevotech.com/*'=>fn()=>throw new \Illuminate\Http\Client\ConnectionException('cURL error 6: Could not resolve host')]);
        $result=$this->send([$o->id]);
        $this->assertCount(1,$result['failed']);
        $this->assertSame('failed',SmsLog::first()->status);
        $this->assertTrue(app(CollectionNotifications::class)->state($o->fresh())['canNotify']);
        $this->fakeHttp(['api.veevotech.com/*'=>Http::response(['STATUS'=>'SUCCESSFUL','MESSAGE_ID'=>'retry'])]);
        $this->assertSame(1,$this->send([$o->id])['sent']);
    }

    public function test_editing_delivery_date_sends_rescheduled_template_after_commit(): void
    {
        $o=$this->order();$old=\App\Services\Dates::format($o->delivery_date);
        $new=now()->addDays(3)->format('Y-m-d');
        app(OrderService::class)->update($o,['delivery_date'=>$new,'edit_version'=>0]);
        $log=SmsLog::where('template_id','due-extended')->firstOrFail();
        $this->assertSame('accepted',$log->status);
        $this->assertStringContainsString('rescheduled from '.$old.' to ', $log->message);
        $this->assertStringNotContainsString('Reason:', $log->message);
        Http::assertSentCount(1);
    }

    public function test_client_defaults_keep_existing_ids_and_saved_switches(): void
    {
        $templates=Settings::defaultSmsTemplates();
        $this->assertSame('READY ORDER REMINDER',collect($templates)->firstWhere('id','collection-reminder')['name']);
        $this->assertSame('DELIVERY RESCHEDULED',collect($templates)->firstWhere('id','due-extended')['name']);
        Settings::put(['sms_templates'=>[['id'=>'order-ready','active'=>false,'text'=>'Custom {orderID}'],['id'=>'payment-received','active'=>true,'text'=>'Payment {paidAmount}']]]);
        $this->assertNull(Settings::activeSmsTemplate('order-ready'));
        $this->assertSame('Payment {paidAmount}',Settings::activeSmsTemplate('payment-received')['text']);
        $this->assertCount(7,Settings::smsTemplates());
    }

    public function test_checked_failed_unknown_attempt_can_retry_without_duplicate_send(): void
    {
        $o=$this->order();$o->update(['order_number'=>'ORD-1056','total'=>18800]);
        $variables=\App\Services\NotificationVariables::variablesForOrder($o);
        $this->assertSame('18,800',$variables['remainingBalance']);
        $message=\App\Services\SmsService::renderTemplate('order-ready',$variables);
        $this->assertStringContainsString('Balance due: Rs 18,800.', $message);
        $this->assertStringNotContainsString('Rs Rs', $message);
        $this->fakeHttp(['api.veevotech.com/*'=>fn()=>throw new \Illuminate\Http\Client\ConnectionException('cURL error 28: Timeout fixture-key')]);
        $this->send([$o->id]);$log=SmsLog::first();
        $this->assertSame('unknown',$log->status);
        $this->assertStringContainsString('Timeout [REDACTED]',$log->api_response);
        $this->assertSame(0,$this->send([$o->id])['sent']);
        $this->travel(3)->minutes();
        $this->postJson('/delivery/sms/'.$log->id.'/resolve',['outcome'=>'failed','confirmation'=>'CHECKED'])->assertOk();
        $this->assertSame('failed',$o->fresh()->ready_sms_state);
        $this->fakeHttp(['api.veevotech.com/*'=>Http::response(['STATUS'=>'SUCCESSFUL','MESSAGE_ID'=>'safe-retry'])]);
        $this->assertSame(1,$this->send([$o->id])['sent']);
        $this->assertSame(0,$this->send([$o->id])['sent']);
        Http::assertSentCount(1);
        $this->assertStringContainsString('safe-retry',SmsLog::latest('id')->first()->api_response);
    }
}


