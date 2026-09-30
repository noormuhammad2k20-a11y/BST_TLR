<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Services\{OrderAutoProgress, Settings};
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Http, Schema};
use Tests\TestCase;

final class OrderAutoProgressTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        (new \ReflectionProperty(\Illuminate\Database\Eloquent\Model::class, 'guardableColumns'))->setValue(null, []);
        config(['database.default'=>'sqlite', 'database.connections.sqlite.database'=>':memory:', 'database.connections.sqlite.url'=>null]);
        DB::purge('sqlite');
        Schema::create('orders', function (Blueprint $t) {
            $t->id(); $t->string('status'); $t->integer('progress')->default(0); $t->integer('edit_version')->default(0);
            $t->dateTime('delivery_date')->nullable(); $t->timestamps(); $t->softDeletes();
        });
        Schema::create('order_status_histories', function (Blueprint $t) {
            $t->id(); $t->integer('order_id'); $t->string('from_status'); $t->string('to_status');
            $t->string('label'); $t->text('note'); $t->string('actor_name'); $t->timestamps();
        });
        (require database_path('migrations/2026_08_07_233018_create_settings_table.php'))->up();
        Settings::flush();
        Settings::put(['verification_before_days'=>0]);
        Http::preventStrayRequests();
        config(['app.timezone'=>Settings::timezone()]);
        date_default_timezone_set(Settings::timezone());
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-09-11 03:00:00', Settings::timezone()));
    }

    protected function tearDown(): void
    {
        $this->travelBack(); Settings::flush(); parent::tearDown();
    }

    private function order(string $status = 'Received'): Order
    {
        return Order::create(['status'=>$status, 'delivery_date'=>now()->addMinutes(5)]);
    }

    public function test_five_minute_order_progresses_at_boundaries_even_when_shop_is_closed(): void
    {
        $order=$this->order(); $service=app(OrderAutoProgress::class);
        $service->run(); $this->assertSame('Received',$order->fresh()->status);
        $this->travel(60)->seconds(); $service->run(); $this->assertSame('Pending',$order->fresh()->status);
        $this->travel(90)->seconds(); $service->run(); $this->assertSame('Stitching',$order->fresh()->status);
        $this->travel(149)->seconds(); $service->run(); $this->assertSame('Stitching',$order->fresh()->status);
        $this->travel(1)->seconds(); $service->run(); $this->assertSame('Ready for Verification',$order->fresh()->status);
        $this->travel(10)->minutes(); $service->run();
        $this->assertSame('Ready for Verification',$order->fresh()->status);
        $this->assertSame(3,$order->statusHistories()->count());
        $this->assertSame(90,$order->fresh()->progress);
    }

    public function test_late_catchup_is_idempotent_and_preserves_manual_ready_and_delivered(): void
    {
        $order=$this->order(); $ready=$this->order('Ready'); $delivered=$this->order('Delivered');
        $this->travel(10)->minutes();
        $this->assertSame(3,app(OrderAutoProgress::class)->run());
        $this->assertSame(0,app(OrderAutoProgress::class)->run());
        $this->assertSame('Ready for Verification',$order->fresh()->status);
        $this->assertSame('Ready',$ready->fresh()->status);
        $this->assertSame('Delivered',$delivered->fresh()->status);
        $this->assertSame(['Pending','Stitching','Ready for Verification'],$order->statusHistories()->pluck('to_status')->all());
    }

    public function test_extended_deadline_never_regresses_work_and_legacy_progress_catches_up(): void
    {
        $order=$this->order('In Progress');
        $order->update(['delivery_date'=>now()->addDay()]);
        $this->travel(10)->minutes(); app(OrderAutoProgress::class)->run();
        $this->assertSame('In Progress',$order->fresh()->status);
        $this->travel(1)->days(); app(OrderAutoProgress::class)->run();
        $this->assertSame('Ready for Verification',$order->fresh()->status);
        $this->assertSame(1,$order->statusHistories()->count());
    }
    public function test_two_day_verification_boundary_and_due_labels_without_sms(): void
    {
        Settings::put(['verification_before_days'=>2]);
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-10-10 15:00:00',Settings::timezone()));
        $order=Order::create(['status'=>'Stitching','delivery_date'=>'2026-10-20 15:00:00']);
        foreach (['Ready','Delivered','Completed','Cancelled','On Hold'] as $closed) {
            Order::create(['status'=>$closed,'delivery_date'=>'2026-10-20 15:00:00']);
        }
        $this->travelTo(now()->setDate(2026,10,17));
        $this->assertSame(0,app(OrderAutoProgress::class)->run());
        $this->travelTo(now()->setDate(2026,10,18)->setTime(14,59,59));
        $this->assertSame(0,app(OrderAutoProgress::class)->run());
        $this->travel(1)->seconds();
        $this->assertSame(1,app(OrderAutoProgress::class)->run());
        $this->assertSame('Ready for Verification',$order->fresh()->status);
        $this->assertSame('Due in 2 Days',\App\Services\DeliveryTiming::describe($order->fresh())['text']);
        $this->travel(1)->days();
        $this->assertSame('Due Tomorrow',\App\Services\DeliveryTiming::describe($order->fresh())['text']);
        $this->travel(1)->days();
        $this->assertSame('Due Today',\App\Services\DeliveryTiming::describe($order->fresh())['text']);
        $this->travel(1)->days();
        $this->assertTrue(\App\Services\DeliveryTiming::describe($order->fresh())['overdue']);
        $this->assertSame(0,app(OrderAutoProgress::class)->run());
        $this->assertSame(1,$order->statusHistories()->count());
        Http::assertNothingSent();
    }

    public function test_short_promises_enter_verification_at_booking_and_setting_is_configurable(): void
    {
        Settings::put(['verification_before_days'=>3]);
        $o=$this->order();
        app(OrderAutoProgress::class)->run();
        $this->assertSame('Ready for Verification',$o->fresh()->status);
        $this->assertTrue($o->statusHistories()->first()->created_at->equalTo($o->created_at));
        $this->assertSame(0,app(OrderAutoProgress::class)->run());
    }

}
