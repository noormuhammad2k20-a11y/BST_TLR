<?php

namespace App\Services\ClothStore;

use App\Models\ClothStore\{Product, ProductLocation, Location, StockTransaction};
use App\Services\Decimal as D;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class InventoryService
{
    public function mainLocation(): int
    {
        $location = Location::where('name', 'Main Store')->where('is_active', true)->first();
        if (!$location) $this->fail('An active Main Store location is required.');
        return $location->id;
    }

    public function move(int $productId, string $quantity, string $reason, string $reference, ?int $locationId = null, ?int $orderItemId = null): void
    {
        DB::transaction(function () use ($productId,$quantity,$reason,$reference,$locationId,$orderItemId) {
            $product = Product::withTrashed()->whereKey($productId)->lockForUpdate()->firstOrFail();
            $rows = ProductLocation::where('cs_product_id',$productId)->orderBy('cs_location_id')->lockForUpdate()->get();
            $sum = '0.00';
            foreach ($rows as $row) {
                if (D::cmp((string)$row->quantity,'0') < 0) $this->fail('Negative location stock requires reconciliation.');
                $sum = D::add($sum,(string)$row->quantity);
            }
            if (D::cmp($sum,(string)$product->stock_quantity) !== 0) $this->fail('Product/location stock differs; run integrity:reconcile before moving this product.');
            if (D::cmp($quantity,'0') === 0) return;
            $out = D::cmp($quantity,'0') < 0;
            $remaining = $out ? D::sub('0',$quantity) : $quantity;
            if (!$out) {
                $locationId ??= $this->mainLocation();
                if (!Location::whereKey($locationId)->where('is_active',true)->exists()) $this->fail('Inactive or missing destination location.');
                $row = ProductLocation::firstOrCreate(['cs_product_id'=>$productId,'cs_location_id'=>$locationId],['quantity'=>'0.00']);
                $rows = collect([$row]);
            } else {
                $active = Location::where('is_active',true)->pluck('id')->all();
                $rows = $rows->filter(fn ($r) => in_array($r->cs_location_id,$active) && ($locationId === null || $r->cs_location_id === $locationId));
                $available = '0.00';
                foreach ($rows as $row) $available = D::add($available,(string)$row->quantity);
                if (D::cmp($available,$remaining)<0) $this->fail('Insufficient stock in eligible locations.');
            }
            foreach ($rows as $row) {
                $part = $out ? D::min((string)$row->quantity,$remaining) : $remaining;
                if (D::cmp($part,'0') === 0) continue;
                $row->quantity = $out ? D::sub((string)$row->quantity,$part) : D::add((string)$row->quantity,$part);
                $row->save();
                $next = $out ? D::sub($sum,$part) : D::add($sum,$part);
                StockTransaction::create(['cs_product_id'=>$productId,'user_id'=>auth()->id(),'type'=>$out?'out':'in',
                    'quantity'=>$part,'previous_qty'=>$sum,'new_qty'=>$next,'reason'=>$reason,'reference'=>$reference,
                    'from_location_id'=>$out?$row->cs_location_id:null,'to_location_id'=>$out?null:$row->cs_location_id]);
                if ($out && $orderItemId) DB::table('cs_inventory_allocations')->insert([
                    'order_item_id'=>$orderItemId,'location_id'=>$row->cs_location_id,'quantity'=>$part,
                    'restored_quantity'=>'0.00','created_at'=>now(),'updated_at'=>now()]);
                $sum=$next; $remaining=D::sub($remaining,$part);
                if (D::cmp($remaining,'0')===0) break;
            }
            $product->forceFill(['stock_quantity'=>$sum])->save();
        });
    }

    public function restore(int $itemId, int $productId, string $quantity, string $reference): void
    {
        $rows = DB::table('cs_inventory_allocations')->where('order_item_id',$itemId)->orderBy('id')->lockForUpdate()->get();
        if ($rows->isEmpty()) {
            $this->move($productId,$quantity,'Historical return to Main Store',$reference,$this->mainLocation());
            return;
        }
        foreach ($rows as $row) {
            $part=D::min($quantity,D::sub($row->quantity,$row->restored_quantity));
            if (D::cmp($part,'0')<=0) continue;
            $this->move($productId,$part,'Sale stock restored',$reference,$row->location_id);
            DB::table('cs_inventory_allocations')->where('id',$row->id)->update(['restored_quantity'=>D::add($row->restored_quantity,$part),'updated_at'=>now()]);
            $quantity=D::sub($quantity,$part);
        }
        if (D::cmp($quantity,'0')>0) $this->fail('Restoration exceeds the recorded sale allocation.');
    }

    private function fail(string $message): never { throw ValidationException::withMessages(['stock'=>$message]); }
}
