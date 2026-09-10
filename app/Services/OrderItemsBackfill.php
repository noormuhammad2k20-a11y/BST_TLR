<?php

namespace App\Services;

use App\Models\{Order, Measurement};
use Illuminate\Support\Facades\DB;

final class OrderItemsBackfill
{
    public function run(Order $order, bool $apply = false): array
    {
        return DB::transaction(function () use ($order, $apply) {
            $order = Order::withTrashed()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($order->items_migrated_at) return ['id' => $order->id, 'state' => 'already migrated'];
            $original = $order->items;
            $items = $original ?: [['name' => $order->garment ?: 'Custom Order', 'qty' => 1]];
            $flags = []; $sum = '0.00'; $rows = [];
            foreach ($items as $position => $item) {
                $qty = max(1, (int)($item['qty'] ?? 1));
                $unit = isset($item['unit_price']) ? Decimal::value((string)$item['unit_price']) : null;
                $amount = $unit !== null ? Decimal::mul($unit, $qty) : (count($items) === 1 ? (string)$order->total : Decimal::value((string)($item['price'] ?? '0')));
                $unit ??= Decimal::ratio($amount, '1', (string)$qty);
                if (count($items) > 1 && !isset($item['unit_price'])) $flags[] = 'Ambiguous price on historical line '.($position + 1).'; retained as line amount.';
                $sum = Decimal::add($sum, $amount);
                $rows[] = ['name' => $item['name'] ?? $order->garment ?? 'Custom Order', 'quantity' => $qty,
                    'unit_price' => $unit, 'subtotal' => $amount, 'fabric' => $item['fabric'] ?? $order->fabric,
                    'style_notes' => $item['style_notes'] ?? $order->style_notes, 'position' => $position,
                    'category' => count($items) === 1 ? $order->productService?->category : null,
                    'product_service_id' => count($items) === 1 ? $order->product_service_id : null];
            }
            $adjustment = Decimal::sub((string)$order->total, $sum);
            if (Decimal::cmp($adjustment, '0') !== 0) $flags[] = 'Historical adjustment '.$adjustment.'; no current tax assumptions.';
            $sheets = Measurement::where('order_id', $order->id)->whereNull('order_item_piece_id')->get();
            $matched = []; $missing = 0;
            foreach ($rows as $row) {
                $created = $apply ? $order->lineItems()->create($row) : null;
                $profile = MeasurementProfiles::all()[MeasurementProfiles::infer($row['name'])];
                for ($n = 1; $n <= $row['quantity']; $n++) {
                    $candidates = count($rows) === 1 ? $sheets->where('piece_no', $n) : collect();
                    $sheet = $candidates->count() === 1 ? $candidates->first() : null;
                    if (!$sheet && $candidates->isEmpty() && count($rows) === 1 && $order->measurement_id) {
                        $saved = Measurement::where('customer_id', $order->customer_id)->find($order->measurement_id);
                        if ($saved && ($saved->is_template || (int)$saved->order_id !== (int)$order->id)) $sheet = $saved;
                    }
                    if ($candidates->count() > 1) $flags[] = 'Ambiguous measurement ownership for piece '.$n.'; not linked.';
                    if ($sheet && (int)$sheet->customer_id !== (int)$order->customer_id) { $flags[] = 'Foreign-customer measurement retained without linking.'; $sheet = null; }
                    if ($sheet) $matched[] = $sheet->id;
                    else $missing++;
                    if (!$apply) continue;
                    $piece = $created->pieces()->create(['piece_no' => $n, 'unit' => in_array($sheet?->unit, ['in','cm']) ? $sheet->unit : 'in', 'profile' => $profile]);
                    if ($sheet) {
                        // Always clone: historical sheets may be shared by other orders or saved templates.
                        $copy = $sheet->replicate();
                        $copy->forceFill(['order_id' => $order->id, 'order_item_piece_id' => $piece->id, 'piece_no' => $n, 'is_template' => false])->save();
                    }
                }
            }
            $unmatched = $sheets->whereNotIn('id', $matched)->pluck('id')->all();
            if ($missing) $flags[] = $missing.' historical pieces have no unambiguous measurements; left blank.';
            if ($unmatched) $flags[] = 'Unmatched measurements retained: '.implode(', ', $unmatched);
            if ($apply) DB::table('orders')->where('id', $order->id)->update([
                'legacy_items' => $original === null ? null : json_encode($original),
                'billing_snapshot' => json_encode(['historical' => true, 'subtotal' => $sum, 'adjustment' => $adjustment, 'total' => $order->total, 'flags' => $flags]),
                'items_migrated_at' => now(),
            ]);
            return ['id' => $order->id, 'state' => $apply ? 'migrated' : 'dry-run', 'items' => count($rows), 'pieces' => array_sum(array_column($rows,'quantity')), 'total' => $order->total, 'adjustment' => $adjustment, 'flags' => $flags];
        });
    }
}
