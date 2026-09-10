<?php

namespace Tests\Integration;

use App\Models\{Customer, Measurement, Order, ProductService, User};
use App\Services\{OrderService, OrderItemsBackfill, PricingService, Settings};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MixedGarmentOrdersTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('INTEGRITY_MYSQL') !== '1') $this->markTestSkipped('Requires isolated MySQL.');
        $this->assertSame('atelier_integrity_test', DB::connection()->getDatabaseName());
        DB::beginTransaction(); Settings::flush();
        if (!is_dir(base_path('dev/artifacts'))) mkdir(base_path('dev/artifacts'),0777,true);
        Settings::put(['sms_enabled'=>false,'tax_enabled'=>false,'service_charge_enabled'=>false]);
        $this->actingAs(User::factory()->create(['role'=>'admin','is_active'=>true]));
    }
    protected function tearDown(): void
    {
        if (getenv('INTEGRITY_MYSQL')==='1') while(DB::transactionLevel()>0) DB::rollBack();
        Settings::flush(); parent::tearDown();
    }
    private function payload(): array
    {
        $customer=Customer::create(['name'=>'Mixed customer','phone'=>'03001234567']);
        $garment=ProductService::create(['name'=>'Test garment '.uniqid(),'type'=>'Service','category'=>'Service','status'=>'Active','price'=>'100','measurement_profile'=>'generic']);
        $accessory=ProductService::create(['name'=>'Test accessory '.uniqid(),'type'=>'Product','category'=>'Other','status'=>'Active','price'=>'50','measurement_profile'=>'accessory','requires_measurements'=>false]);
        $piece=['unit'=>'in','values'=>['length'=>40,'chest'=>38,'waist'=>34,'chest_losing'=>2,'waist_losing'=>2,'hip_losing'=>2]];
        return ['customer_id'=>$customer->id,'advance'=>'50','delivery_date'=>now()->addDays(5)->toDateString(),'garments'=>[
            ['product_service_id'=>$garment->id,'quantity'=>2,'unit_price'=>'100.00','pieces'=>[$piece,$piece]],
            ['product_service_id'=>$accessory->id,'quantity'=>1,'unit_price'=>'50.00','pieces'=>[['unit'=>'cm','values'=>[]]]],
        ]];
    }
    private function edit(Order $order): array
    {
        return ['edit_version'=>$order->fresh()->edit_version,'garments'=>$order->lineItems()->with('pieces.measurement')->get()->map(fn($i)=>[
            'id'=>$i->id,'product_service_id'=>$i->product_service_id,'quantity'=>$i->quantity,'unit_price'=>$i->unit_price,
            'pieces'=>$i->pieces->map(fn($p)=>['id'=>$p->id,'unit'=>$p->unit,'values'=>$p->measurement?->only(Measurement::FIELDS)??[]])->all(),
        ])->all()];
    }
    public function test_mixed_prices_independent_measurements_accessory_and_invoice(): void
    {
        $order=app(OrderService::class)->create($this->payload());
        $this->assertSame('250.00',$order->total);
        $this->assertSame(3,$order->quantity);
        $this->assertSame(2,$order->lineItems()->count());
        $this->assertSame(2,Measurement::where('order_id',$order->id)->count());
        $this->assertSame(1,$order->payments()->count());
        $this->assertSame(250.0,array_sum(array_column(PricingService::allocatedItems($order),'revenue')));
        Settings::put(['tax_enabled'=>true,'tax_rate'=>20]);
        $this->assertEquals(250,PricingService::forOrder($order)['total']);
        $this->assertCount(2,PricingService::invoiceItems($order));
    }
    public function test_http_contract_and_rendered_editor(): void
    {
        $payload=$this->payload();
        $response=$this->postJson('/orders',$payload)->assertCreated();
        $id=$response->json('order.db_id');
        $this->assertCount(2,$response->json('order.garments'));
        $this->getJson('/orders/'.$id)->assertOk()->assertJsonPath('order.qty',3);
        $this->getJson('/payments-billing/'.$id.'/invoice')->assertOk()->assertJsonCount(2,'invoice.items');
        $this->getJson('/orders/'.$id.'/receipt')->assertOk()->assertJsonCount(2,'receipt.items');
        file_put_contents(base_path('dev/artifacts/mixed-printing-browser.html'),$this->get('/printing-center')->assertOk()->getContent());
        $html=$this->get('/orders')->assertOk()->getContent();
        file_put_contents(base_path('dev/artifacts/mixed-orders-browser.html'),$html);
        $order=Order::findOrFail($id);$edit=$this->edit($order);
        $edit['status']='Pending';$edit['priority']='Normal';
        $this->putJson('/orders/'.$id,$edit)->assertOk();
        $this->putJson('/orders/'.$id,$edit)->assertUnprocessable()->assertJsonValidationErrors('edit_version');
    }

    public function test_saved_measurements_copy_without_transferring_ownership(): void
    {
        $payload=$this->payload();
        $sheet=Measurement::create(['customer_id'=>$payload['customer_id'],'garment_type'=>'Generic stitching','unit'=>'cm','is_template'=>true,
            'length'=>100,'chest'=>96,'waist'=>86,'chest_losing'=>2,'waist_losing'=>2,'hip_losing'=>2]);
        foreach($payload['garments'][0]['pieces'] as &$piece) {$piece['measurement_id']=$sheet->id;$piece['values']=[];} unset($piece);
        $order=app(OrderService::class)->create($payload);
        $copies=$order->lineItems->first()->pieces->map->measurement;
        $this->assertCount(2,$copies);$this->assertNotSame($copies[0]->id,$copies[1]->id);
        $this->assertSame('cm',$copies[1]->unit);$this->assertNull($sheet->fresh()->order_item_piece_id);
        $foreign=Measurement::create(['customer_id'=>Customer::create(['name'=>'Other','phone'=>'03009999999'])->id,'garment_type'=>'Generic','unit'=>'in']);
        $payload['garments'][0]['pieces'][0]['measurement_id']=$foreign->id;
        $this->expectException(ValidationException::class);app(OrderService::class)->create($payload);
    }
    public function test_quantity_removal_archives_only_trailing_piece(): void
    {
        $order=app(OrderService::class)->create($this->payload()); $edit=$this->edit($order);
        $first=$edit['garments'][0]['pieces'][0]['id']; $removed=$edit['garments'][0]['pieces'][1]['id'];
        $edit['garments'][0]['quantity']=1; array_pop($edit['garments'][0]['pieces']);
        app(OrderService::class)->update($order,$edit);
        $this->assertDatabaseHas('order_item_pieces',['id'=>$first,'deleted_at'=>null]);
        $this->assertNotNull(DB::table('order_item_pieces')->where('id',$removed)->value('deleted_at'));
        $this->assertDatabaseHas('measurements',['order_item_piece_id'=>$removed]);
    }
    public function test_forged_piece_rolls_back_every_write(): void
    {
        $order=app(OrderService::class)->create($this->payload());$edit=$this->edit($order);
        $edit['garments'][0]['pieces'][0]['id']=999999999;
        try {app(OrderService::class)->update($order,$edit);$this->fail('Must reject forged ownership');} catch(ValidationException $e) {$this->assertArrayHasKey('garments.0.pieces.0.id',$e->errors());}
        $this->assertSame('250.00',$order->fresh()->total);
    }
    public function test_stale_and_completed_and_paid_locks(): void
    {
        $order=app(OrderService::class)->create($this->payload());$edit=$this->edit($order);
        $order->update(['notes'=>'Another counter']);
        try {app(OrderService::class)->update($order,$edit);$this->fail('Must reject stale edit');} catch(ValidationException $e) {$this->assertArrayHasKey('edit_version',$e->errors());}
        $edit=$this->edit($order); foreach($edit['garments'] as &$row) $row['unit_price']='1.00'; unset($row);
        try {app(OrderService::class)->update($order,$edit);$this->fail('Must reject below paid');} catch(ValidationException $e) {$this->assertArrayHasKey('total',$e->errors());}
        $order->update(['status'=>'Completed']);$edit=$this->edit($order);
        try {app(OrderService::class)->update($order,$edit);$this->fail('Must lock completed');} catch(ValidationException $e) {$this->assertArrayHasKey('garments',$e->errors());}
        $this->assertSame('250.00',$order->fresh()->total);
    }
    public function test_required_profile_rolls_back_new_customer_and_order(): void
    {
        $payload=$this->payload();$payload['garments'][0]['pieces'][0]['values']=[];
        $before=Order::count();
        try {app(OrderService::class)->create($payload);$this->fail('Must require essential measurements');} catch(ValidationException $e) {$this->assertNotEmpty($e->errors());}
        $this->assertSame($before,Order::count());
    }
    public function test_backfill_preserves_legacy_price_and_shared_measurement(): void
    {
        $payload=$this->payload();$customer=$payload['customer_id'];
        $sheet=Measurement::create(['customer_id'=>$customer,'garment_type'=>'Generic','unit'=>'in','chest'=>38,'is_template'=>true]);
        $order=Order::create(['customer_id'=>$customer,'measurement_id'=>$sheet->id,'items'=>[['name'=>'Legacy','qty'=>3,'price'=>100]],'total'=>300,'advance'=>0,'balance'=>300,'status'=>'Pending']);
        $before=$order->getAttributes();$service=app(OrderItemsBackfill::class);
        $this->assertSame(3,$service->run($order)['pieces']);$this->assertSame(0,$order->lineItems()->count());
        $service->run($order,true);$this->assertSame('already migrated',$service->run($order,true)['state']);
        $this->assertSame('300.00',$order->fresh()->lineItems->first()->subtotal);
        foreach(['total','advance','balance','items','updated_at'] as $key) $this->assertEquals($before[$key],$order->fresh()->getAttributes()[$key]);
        $this->assertTrue($sheet->fresh()->is_template);$this->assertNull($sheet->fresh()->order_item_piece_id);
        $this->assertSame(3,Measurement::where('order_id',$order->id)->whereNotNull('order_item_piece_id')->count());
    }

    public function test_measurement_only_edit_keeps_billing_snapshot_and_duplicate_rows(): void
    {
        $payload=$this->payload();$payload['garments'][]=$payload['garments'][0];
        $order=app(OrderService::class)->create($payload);$snapshot=$order->billing_snapshot;
        $this->assertSame(3,$order->lineItems()->count());
        Settings::put(['tax_enabled'=>true,'tax_rate'=>20,'tax_inclusive'=>false]);
        $edit=$this->edit($order);$edit['garments'][0]['pieces'][0]['values']['length']=41;
        $updated=app(OrderService::class)->update($order,$edit);
        $this->assertSame('450.00',$updated->total);$this->assertSame($snapshot,$updated->billing_snapshot);
        $this->assertSame(450.0,array_sum(array_column(PricingService::allocatedItems($updated),'revenue')));
    }

    public function test_order_owned_sheet_does_not_fill_missing_historical_pieces(): void
    {
        $payload=$this->payload();
        $order=Order::create(['customer_id'=>$payload['customer_id'],'items'=>[['name'=>'Legacy','qty'=>3,'price'=>100]],'total'=>300,'advance'=>0,'balance'=>300,'status'=>'Pending']);
        $sheet=Measurement::create(['customer_id'=>$order->customer_id,'order_id'=>$order->id,'piece_no'=>1,'garment_type'=>'Generic','unit'=>'in','chest'=>38]);
        $order->update(['measurement_id'=>$sheet->id]);
        $result=app(OrderItemsBackfill::class)->run($order,true);
        $this->assertSame(1,Measurement::where('order_id',$order->id)->whereNotNull('order_item_piece_id')->count());
        $this->assertStringContainsString('2 historical pieces',implode(' ',$result['flags']));
    }

    public function test_backup_round_trip_restores_the_relationship_graph(): void
    {
        $order=app(OrderService::class)->create($this->payload());
        $export=\App\Services\BackupService::payload();$data=[];$offset=1000000;
        $itemIds=$order->lineItems->pluck('id')->all();$pieceIds=$order->lineItems->flatMap->pieces->pluck('id')->all();
        foreach(['orders','order_items','order_item_pieces','measurements','payments'] as $type) {
            $data[$type]=array_values(array_filter($export['data'][$type],fn($r)=>match($type){
                'orders'=>$r['id']===$order->id,'order_items'=>$r['order_id']===$order->id,
                'order_item_pieces'=>in_array($r['order_item_id'],$itemIds),
                'measurements'=>in_array($r['order_item_piece_id'],$pieceIds),
                'payments'=>$r['order_id']===$order->id,
            }));
            foreach($data[$type] as &$row) {
                $row['id']+=$offset;
                foreach(['order_id','order_item_id','order_item_piece_id','measurement_id'] as $key) if(!empty($row[$key])) $row[$key]+=$offset;
                if($type==='orders') {$row['order_number']='ROUND-'.$row['id'];$row['invoice_number']='ROUND-INV-'.$row['id'];}
                if($type==='payments') $row['invoice_id']='ROUND-INV-'.($order->id+$offset);
            }unset($row);
        }
        $export['data']=$data;$export['settings']=[];
        $path=base_path('dev/artifacts/mixed-backup-roundtrip.json');file_put_contents($path,json_encode($export));
        $result=\App\Services\BackupService::restore($path,array_keys($data));
        $this->assertTrue($result['success'],$result['message']);
        $copy=Order::findOrFail($order->id+$offset);
        $this->assertSame(3,$copy->quantity);$this->assertSame('250.00',$copy->total);
        $this->assertEquals(50,$copy->paid_amount);
        $this->assertSame(2,Measurement::where('order_id',$copy->id)->whereNotNull('order_item_piece_id')->count());
        $again=\App\Services\BackupService::restore($path,array_keys($data));
        $this->assertTrue($again['success']);$this->assertSame(0,array_sum($again['imported']));
    }

    public function test_catalogue_reconciliation_and_seeding_are_idempotent(): void
    {
        $payload=$this->payload();$product=ProductService::find($payload['garments'][0]['product_service_id']);
        $alias=DB::table('product_services')->insertGetId(array_merge($product->getAttributes(),['id'=>null,'normalized_name'=>null,'name'=>'  '.strtoupper($product->name).'  ']));
        \App\Services\CatalogueIdentity::reconcile(true);
        $this->assertEquals($product->id,ProductService::find($alias)->canonical_id);
        $seed=\App\Services\CatalogueIdentity::seed(strtoupper($product->name),['price'=>999,'type'=>'Service']);
        $this->assertSame($product->id,$seed->id);$this->assertSame('100.00',$seed->price);
        \App\Services\CatalogueIdentity::reconcile(true);
        $this->assertEquals($product->id,ProductService::find($alias)->canonical_id);
    }

    public function test_work_credit_excludes_accessories_and_sms_contains_all_garments(): void
    {
        $payload=$this->payload();$staff=\App\Models\Staff::create(['name'=>'Mixed payroll','salary_type'=>'Per Suit','per_suit_rate'=>'20']);
        $payload['staff_id']=$staff->id;$order=app(OrderService::class)->create($payload);
        $variables=\App\Services\NotificationVariables::variablesForOrder($order);
        $this->assertSame('3',$variables['quantity']);
        foreach($order->lineItems as $item) $this->assertStringContainsString($item->name,$variables['garmentSummary']);
        $edit=$this->edit($order);$edit['status']='Completed';app(OrderService::class)->update($order,$edit);
        $this->assertDatabaseHas('staff_work_logs',['order_id'=>$order->id,'quantity'=>2,'amount'=>'40.00']);
        app(OrderService::class)->update($order,['notes'=>'Keep original work credit']);
        $this->assertSame(1,\App\Models\StaffWorkLog::where('order_id',$order->id)->count());
    }

    public function test_profile_visibility_and_new_order_quantity_limits(): void
    {
        $profiles=\App\Services\MeasurementProfiles::all();
        $this->assertNotContains('sleeve_length',$profiles['waistcoat']['fields']);
        $this->assertContains('thigh',$profiles['trouser']['fields']);
        $this->assertSame([],$profiles['accessory']['required']);
        $payload=$this->payload();$payload['garments'][0]['quantity']=21;
        $payload['garments'][0]['pieces']=array_fill(0,21,$payload['garments'][0]['pieces'][0]);
        $this->expectException(ValidationException::class);app(OrderService::class)->create($payload);
    }

    public function test_legacy_flat_http_requests_require_measurements_and_normalize_pieces(): void
    {
        $p=$this->payload();$flat=['customer_id'=>$p['customer_id'],'product_service_id'=>$p['garments'][0]['product_service_id'],
            'garment'=>'Generic stitching','quantity'=>2,'total'=>'200','advance'=>'0','delivery_date'=>$p['delivery_date']];
        $this->postJson('/orders',$flat)->assertUnprocessable();
        $flat['measurements']=$p['garments'][0]['pieces'][0]['values'];
        $response=$this->postJson('/orders',$flat)->assertCreated();
        $this->assertCount(1,$response->json('order.garments'));
        $this->assertCount(2,$response->json('order.garments.0.pieces'));
        $this->assertEquals(40,$response->json('order.garments.0.pieces.1.values.length'));
    }
}
