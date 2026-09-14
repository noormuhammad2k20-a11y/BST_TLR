<?php
namespace Tests\Feature;

use App\Models\{Customer, Measurement, Order, OrderItem, OrderItemPiece};
use App\Services\MeasurementEditor;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class MeasurementEditorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default'=>'sqlite','database.connections.sqlite.database'=>':memory:','database.connections.sqlite.url'=>null]); DB::purge('sqlite');
        Schema::create('customers',function(Blueprint $t){$t->id();$t->string('name');$t->string('code')->nullable();$t->timestamps();$t->softDeletes();});
        Schema::create('orders',function(Blueprint $t){$t->id();$t->integer('customer_id');$t->integer('measurement_id')->nullable();$t->string('status');$t->integer('progress')->default(0);$t->integer('edit_version')->default(0);$t->timestamp('completed_at')->nullable();$t->timestamp('delivered_at')->nullable();$t->timestamps();$t->softDeletes();});
        Schema::create('order_items',function(Blueprint $t){$t->id();$t->integer('order_id');$t->string('name');$t->timestamps();$t->softDeletes();});
        Schema::create('order_item_pieces',function(Blueprint $t){$t->id();$t->integer('order_item_id');$t->integer('piece_no');$t->string('unit');$t->json('profile');$t->timestamps();$t->softDeletes();});
        Schema::create('measurements',function(Blueprint $t){$t->id();$t->integer('customer_id');$t->integer('order_id')->nullable();$t->integer('order_item_piece_id')->nullable()->unique();$t->string('garment_type');$t->string('unit');$t->string('tailor')->nullable();$t->text('notes')->nullable();$t->json('details')->nullable();foreach(Measurement::FIELDS as $f)$t->decimal($f,8,2)->nullable();$t->timestamps();});
        Schema::create('staff_work_logs',fn(Blueprint $t)=>$t->integer('order_id'));
    }

    private function fixture(string $status='Stitching'): array
    {
        $customer=Customer::create(['name'=>'Faizal']);
        $order=Order::create(['customer_id'=>$customer->id,'status'=>$status]);
        $item=OrderItem::create(['order_id'=>$order->id,'name'=>'Alteration and Fitting']);
        $piece=OrderItemPiece::create(['order_item_id'=>$item->id,'piece_no'=>1,'unit'=>'in','profile'=>['key'=>'alteration']]);
        $m=Measurement::create(['customer_id'=>$customer->id,'order_id'=>$order->id,'order_item_piece_id'=>$piece->id,'garment_type'=>$item->name,'unit'=>'in','chest'=>40,'details'=>['custom_length'=>15]]);
        $order->update(['measurement_id'=>$m->id]);
        return [$m,$order,$piece];
    }

    private function change(Measurement $m, array $extra=[]): Measurement
    {
        return app(MeasurementEditor::class)->update($m,array_merge(['customer_id'=>$m->customer_id,'garment_type'=>$m->garment_type,'unit'=>'cm','chest'=>42.25,'notes'=>'Corrected fitting'], $extra));
    }

    public function test_active_piece_updates_in_database_and_invalidates_stale_order_forms(): void
    {
        [$m,$order,$piece]=$this->fixture();$version=$order->edit_version;
        $this->change($m);
        $this->assertEquals(42.25,$m->fresh()->chest);
        $this->assertSame('cm',$piece->fresh()->unit);
        $this->assertEquals($m->id,$piece->fresh()->measurement->id);
        $this->assertSame($version+1,$order->fresh()->edit_version);
        $this->assertSame('Stitching',$order->fresh()->status);
        $this->assertSame(['custom_length'=>15],$m->fresh()->details);
    }

    public function test_completed_order_retains_snapshot_while_customer_saved_set_can_be_edited_repeatedly(): void
    {
        [$m,$order,$piece]=$this->fixture('Delivered');
        $saved=$this->change($m);
        $this->assertSame($m->id,$saved->id);
        $this->assertNull($saved->order_item_piece_id);
        $this->assertEquals(42.25,$saved->chest);
        $snapshot=$piece->fresh()->measurement;
        $this->assertEquals(40,$snapshot->chest);
        $this->assertSame('in',$snapshot->unit);
        $this->assertEquals($snapshot->id,$order->fresh()->measurement_id);
        $this->assertEquals([$m->id],Measurement::savedSets()->pluck('id')->all());
        $this->change($saved,['chest'=>44]);
        $this->assertEquals(44,$m->fresh()->chest);
        $this->assertSame(2,Measurement::count());
        $this->assertEquals(40,$snapshot->fresh()->chest);
        $this->assertSame('Delivered',$order->fresh()->status);
    }

    public function test_linked_measurement_cannot_move_to_another_customer(): void
    {
        [$m]=$this->fixture();
        try {$this->change($m,['customer_id'=>999]);$this->fail('Changed order ownership');}
        catch(ValidationException $e){$this->assertArrayHasKey('customer_id',$e->errors());}
        $this->assertEquals(40,$m->fresh()->chest);
        $this->assertSame(1,Measurement::count());
    }
    public function test_library_updates_latest_set_without_creating_another_duplicate(): void
    {
        [$m]=$this->fixture();
        $olderCount=Measurement::count();
        $data=['customer_id'=>$m->customer_id,'garment_type'=>$m->garment_type,'unit'=>'in','chest'=>45];
        $saved=app(\App\Services\MeasurementLibrary::class)->save($m->customer,$data);
        $again=app(\App\Services\MeasurementLibrary::class)->save($m->customer,array_merge($data,['chest'=>46]));
        $this->assertSame($m->id,$saved->id);
        $this->assertSame($saved->id,$again->id);
        $this->assertSame($olderCount,Measurement::count());
        $this->assertEquals(46,$m->fresh()->chest);
        $copy=$m->replicate();$copy->order_id=null;$copy->order_item_piece_id=null;$copy->save();
        $latest=app(\App\Services\MeasurementLibrary::class)->save($m->customer,$data);
        $this->assertSame($copy->id,$latest->id);
        $this->assertSame(2,Measurement::count());
        $this->assertSame(1,Measurement::savedSets()->get()->unique(fn($m)=>\App\Services\MeasurementLibrary::key($m))->count());
    }

}
