<?php

namespace App\Services;

use App\Models\{Measurement, Order, OrderItem, OrderItemPiece};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class MeasurementEditor
{
    public function update(Measurement $measurement, array $data): Measurement
    {
        return DB::transaction(function () use ($measurement, $data) {
            // Match the order editor's lock order to prevent stale order forms overwriting this edit.
            $piece = $measurement->piece;
            $item = $piece ? OrderItem::withTrashed()->find($piece->order_item_id) : null;
            $orderId = $item?->order_id ?? $measurement->order_id;
            $order = $orderId ? Order::withTrashed()->whereKey($orderId)->lockForUpdate()->firstOrFail() : null;
            $measurement = Measurement::whereKey($measurement->id)->lockForUpdate()->firstOrFail();
            $piece = $measurement->order_item_piece_id
                ? OrderItemPiece::withTrashed()->whereKey($measurement->order_item_piece_id)->lockForUpdate()->firstOrFail() : null;

            if ($order && (int) $data['customer_id'] !== (int) $order->customer_id) {
                throw ValidationException::withMessages(['customer_id' => 'This measurement belongs to the order customer. Create a separate measurement for another customer.']);
            }

            if ($order && ($order->items_locked || $order->trashed() || $piece?->trashed() || $item?->trashed()
                || $data['garment_type'] !== $measurement->garment_type)) {
                // Keep the same saved-set ID in the customer library; retain the previous values on the order.
                $snapshot = $measurement->replicate();
                $measurement->forceFill(['order_item_piece_id' => null, 'order_id' => null])->save();
                $snapshot->save();
                if ($piece) {
                    $piece->profile = array_merge($piece->profile ?? [], ['saved_measurement_id' => $measurement->id]);
                    $piece->save();
                }
                if ((int) $order->measurement_id === (int) $measurement->id) {
                    $order->measurement_id = $snapshot->id;
                }
            } elseif ($piece && isset($data['unit'])) {
                $piece->unit = $data['unit'];
                $piece->save();
            }

            // Preserve garment-specific fields that are not exposed by the general measurement form.
            $details = $measurement->details ?? [];
            foreach (Measurement::FIELDS as $field) {
                if (array_key_exists($field, $data) && array_key_exists($field, $details)) $details[$field] = $data[$field];
            }
            $measurement->fill($data);
            $measurement->details = $details;
            $measurement->save();
            if ($order) {
                $order->edit_version++;
                $order->save();
            }
            return $measurement->load('customer');
        });
    }
}
