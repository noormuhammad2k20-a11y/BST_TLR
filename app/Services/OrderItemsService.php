<?php

namespace App\Services;

use App\Models\{Order, OrderItem, Measurement, ProductService};
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class OrderItemsService
{
    public function normalizeLegacyUpdate(Order $order, array $data): array
    {
        $keys=['total','garment','product_service_id','fabric','style_notes'];
        if (!array_intersect(array_keys($data),$keys)) return $data;
        $items=$order->lineItems()->with('pieces.measurement')->get();
        if ($items->count()!==1) $this->fail('garments','Use garment rows to edit a mixed order.');
        $item=$items->first();
        $changed=false;
        foreach(['garment'=>'name','product_service_id'=>'product_service_id','fabric'=>'fabric','style_notes'=>'style_notes'] as $key=>$attribute) {
            if (isset($data[$key]) && (string)$data[$key] !== (string)$item->{$attribute}) $changed=true;
        }
        $priceChanged=isset($data['total']) && Decimal::cmp((string)$data['total'],(string)$order->total)!==0;
        if (!$changed && !$priceChanged) {foreach($keys as $key) unset($data[$key]);return $data;}
        if ($order->items_locked) $this->fail('garments','Garment and price changes are locked after completion or credited work.');
        $row=['id'=>$item->id,'product_service_id'=>$data['product_service_id']??$item->product_service_id,
            'quantity'=>$item->quantity,'unit_price'=>$priceChanged?Decimal::ratio((string)$data['total'],'1',(string)$item->quantity):$item->unit_price,
            'fabric'=>$data['fabric']??$item->fabric,'style_notes'=>$data['style_notes']??$item->style_notes,
            'pieces'=>$item->pieces->map(fn($p)=>['id'=>$p->id,'unit'=>$p->unit,'values'=>$p->measurement?array_merge($p->measurement->only(Measurement::FIELDS),$p->measurement->details??[]):[]])->all()];
        foreach($keys as $key) unset($data[$key]);
        $data['garments']=[$row];
        $data['edit_version']??=$order->edit_version;
        return $data;
    }
    public static function rules(): array
    {
        return [
            'edit_version' => ['sometimes','integer','min:0'],
            'garments' => ['sometimes','array','min:1','max:50'],
            'garments.*' => ['array:id,client_key,product_service_id,quantity,unit_price,fabric,style_notes,pieces'],
            'garments.*.id' => ['nullable','integer','distinct'],
            'garments.*.client_key' => ['nullable','string','max:100'],
            'garments.*.product_service_id' => ['nullable','integer','exists:product_services,id'],
            'garments.*.quantity' => ['required','integer','min:1','max:10000'],
            'garments.*.unit_price' => ['required','numeric','min:0','max:99999999','decimal:0,2'],
            'garments.*.fabric' => ['nullable','string','max:255'],
            'garments.*.style_notes' => ['nullable','string','max:2000'],
            'garments.*.pieces' => ['required','array','min:1','max:10000'],
            'garments.*.pieces.*' => ['array:id,client_key,unit,measurement_id,values,saved_changes'],
            'garments.*.pieces.*.id' => ['nullable','integer','distinct'],
            'garments.*.pieces.*.client_key' => ['nullable','string','max:100'],
            'garments.*.pieces.*.unit' => ['required','in:in,cm'],
            'garments.*.pieces.*.measurement_id' => ['nullable','integer'],
            'garments.*.pieces.*.saved_changes' => ['sometimes','array:values,unit'],
            'garments.*.pieces.*.saved_changes.values' => ['sometimes','array'],
            'garments.*.pieces.*.saved_changes.unit' => ['sometimes','in:in,cm'],
            'garments.*.pieces.*.values' => ['present','array'],
        ];
    }

    public function prepare(array $data, int $customerId, ?Order $order = null): array
    {
        Validator::make($data, self::rules())->validate();
        foreach (['garment','product_service_id','quantity','fabric','style_notes','measurements','measurement_id','pieces'] as $key) {
            if (array_key_exists($key, $data)) $this->fail($key, 'Submit garment fields inside garments only.');
        }
        $existing = $order?->lineItems()->with('pieces.measurement')->get()->keyBy('id') ?? collect();
        $totalQuantity = array_sum(array_column($data['garments'], 'quantity'));
        if ($totalQuantity > max(200, $existing->sum('quantity'))) $this->fail('garments', 'An order may contain at most 200 pieces.');
        $rows = []; $subtotal = '0.00'; $repriced = count($data['garments']) !== $existing->count();
        foreach ($data['garments'] as $i => $row) {
            $row['quantity'] = (int)$row['quantity'];
            $old = isset($row['id']) ? $existing->get($row['id']) : null;
            if (!empty($row['id']) && !$old) $this->fail("garments.$i.id", 'This item does not belong to this order.');
            if ($row['quantity'] > max(20, $old?->quantity ?? 0)) $this->fail("garments.$i.quantity", 'A new garment row may contain at most 20 pieces.');
            if (count($row['pieces']) !== $row['quantity']) $this->fail("garments.$i.pieces", 'Provide exactly one piece per quantity unit.');
            if (empty($row['product_service_id']) && (!$old || $old->product_service_id)) $this->fail("garments.$i.product_service_id", 'Select a garment.');
            $product = !empty($row['product_service_id']) ? ProductService::findOrFail($row['product_service_id']) : new ProductService(['name'=>$old->name,'category'=>$old->category,'status'=>'Active']);
            if ((!empty($product->canonical_id) || $product->status !== 'Active') && $old?->product_service_id !== $product->id) $this->fail("garments.$i.product_service_id", 'Select an active canonical garment.');
            $profile = $old && $old->product_service_id === $product->id && $old->pieces->first()
                ? $old->pieces->first()->profile : MeasurementProfiles::forProduct($product);
            foreach ($row['pieces'] as $j => &$piece) {
                $previous = !empty($piece['id']) ? $old?->pieces->firstWhere('id', $piece['id']) : null;
                if (!empty($piece['id']) && !$previous) $this->fail("garments.$i.pieces.$j.id", 'This piece does not belong to this garment.');
                if ($previous && $previous->piece_no !== $j + 1) $this->fail("garments.$i.pieces.$j.id", 'Retained pieces cannot be reordered.');
                if ($old && $j < min($old->quantity, $row['quantity']) && $old->pieces->get($j)?->id !== ($piece['id'] ?? null)) $this->fail("garments.$i.pieces.$j.id", 'Retain existing piece IDs when changing quantity.');
                if (!empty($piece['measurement_id'])) {
                    $source = Measurement::savedSets()->where('customer_id', $customerId)->lockForUpdate()->find($piece['measurement_id']);
                    if (!$source) $this->fail("garments.$i.pieces.$j.measurement_id", 'Select a measurement belonging to this customer.');
                    $sourceProfile = $source->piece?->profile['key'] ?? MeasurementProfiles::infer($source->garment_type ?? '');
                    if ($sourceProfile !== $profile['key']) $this->fail("garments.$i.pieces.$j.measurement_id", 'The saved measurement profile is incompatible.');
                    $sourceProduct = $source->piece?->item?->product_service_id;
                    $normalizeName = fn ($name) => preg_replace('/\s+/u', ' ', mb_strtolower(trim($name ?? '')));
                    if ($sourceProduct ? $sourceProduct != $product->id : $normalizeName($source->garment_type) !== $normalizeName($product->name)) {
                        $this->fail("garments.$i.pieces.$j.measurement_id", 'Select a saved measurement for this garment.');
                    }
                    $piece['values'] = array_merge($source->only(Measurement::FIELDS), $source->details ?? []);
                    $piece['unit'] = $source->unit;
                    $changes = array_map(fn ($value) => $value === '' ? null : $value, $piece['saved_changes']['values'] ?? []);
                    if (isset($piece['saved_changes']['values'])) $piece['saved_changes']['values'] = $changes;
                    if (array_diff(array_keys($changes), $profile['fields'])) $this->fail("garments.$i.pieces.$j.saved_changes", 'Only measurements for this garment may be updated.');
                    $piece['values'] = array_replace($piece['values'], $changes);
                    $piece['unit'] = $piece['saved_changes']['unit'] ?? $source->unit;
                } elseif (!empty($piece['saved_changes'])) {
                    $this->fail("garments.$i.pieces.$j.measurement_id", 'Select the saved measurement to update.');
                }
                $values = array_intersect_key($piece['values'], array_flip($profile['fields']));
                $unchangedLegacy = $previous && $old->product_service_id === $product->id
                    && $previous->unit === $piece['unit'] && $this->sameValues($values, $previous->measurement, $profile['fields']);
                $rules = [];
                foreach ($profile['fields'] as $field) $rules[$field] = [!$unchangedLegacy && in_array($field, $profile['required']) ? 'required' : 'nullable','numeric','min:0','max:999','decimal:0,'.Settings::measurementDecimals()];
                $validator = Validator::make($values, $rules, [], Measurement::displayProfile($profile)['labels'] ?? Measurement::labels());
                if ($validator->fails()) foreach ($validator->errors()->messages() as $field => $errors) $this->fail("garments.$i.pieces.$j.values.$field", $errors[0]);
                if (!$unchangedLegacy && $profile['at_least_one'] && !count(array_filter($values, fn($v) => $v !== null && $v !== ''))) $this->fail("garments.$i.pieces.$j.values", 'Enter at least one alteration measurement.');
                $piece['values'] = $values;
                $piece['profile'] = $profile;
                if (!empty($piece['measurement_id']) && $previous?->measurement?->id != $piece['measurement_id']) {
                    $piece['profile']['saved_measurement_id'] = (int) $piece['measurement_id'];
                }
                $piece['unchanged'] = $unchangedLegacy;
            }
            unset($piece);
            $row['name'] = $old && $old->product_service_id === $product->id ? $old->name : $product->name;
            $row['category'] = $old && $old->product_service_id === $product->id ? $old->category : $product->category;
            $row['unit_price'] = Decimal::value((string)$row['unit_price']);
            $priceChanged = !$old || $old->quantity !== $row['quantity'] || Decimal::cmp($old->unit_price,$row['unit_price']) !== 0;
            $repriced = $repriced || $priceChanged;
            $row['subtotal'] = $priceChanged ? Decimal::mul($row['unit_price'], $row['quantity']) : $old->subtotal;
            $subtotal = Decimal::add($subtotal, $row['subtotal']);
            $rows[] = $row;
        }
        return ['rows' => $rows, 'subtotal' => $subtotal, 'repriced' => $repriced];
    }

    private function sameValues(array $values, ?Measurement $measurement, array $fields): bool
    {
        if (!$measurement) return !count(array_filter($values, fn($v) => $v !== null && $v !== ''));
        $stored = array_intersect_key(array_merge($measurement->only(Measurement::FIELDS), $measurement->details ?? []), array_flip($fields));
        foreach (array_unique(array_merge(array_keys($stored), array_keys($values))) as $key) {
            $a = $stored[$key] ?? null; $b = $values[$key] ?? null;
            if (($a === null || $a === '') && ($b === null || $b === '')) continue;
            if (!is_numeric($a) || !is_numeric($b) || (float)$a !== (float)$b) return false;
        }
        return true;
    }

    public function write(Order $order, array $rows): void
    {
        $kept = []; $json = []; $firstMeasurement = null;
        foreach ($rows as $i => $row) {
            $item = !empty($row['id']) ? $order->lineItems()->findOrFail($row['id']) : new OrderItem(['order_id' => $order->id]);
            $item->fill(array_intersect_key($row, array_flip(['product_service_id','name','category','quantity','unit_price','subtotal','fabric','style_notes'])));
            $item->position = $i; $item->save(); $kept[] = $item->id;
            $pieceIds = [];
            foreach ($row['pieces'] as $j => $pc) {
                if (!empty($pc['measurement_id']) && !empty($pc['saved_changes'])) {
                    // Update the original set in the order transaction; omitted fields stay untouched.
                    $source = Measurement::where('customer_id', $order->customer_id)->lockForUpdate()->findOrFail($pc['measurement_id']);
                    $changes = $pc['saved_changes']['values'] ?? [];
                    $columns = array_intersect_key($changes, array_flip(Measurement::FIELDS));
                    $details = array_diff_key($changes, array_flip(Measurement::FIELDS));
                    $source->fill($columns);
                    if ($details) $source->details = array_replace($source->details ?? [], $details);
                    if (isset($pc['saved_changes']['unit'])) $source->unit = $pc['saved_changes']['unit'];
                    $source->save();
                    \Illuminate\Support\Facades\Cache::forget('measurements.stats');
                }
                $piece = !empty($pc['id']) ? $item->pieces()->findOrFail($pc['id']) : $item->pieces()->make();
                $piece->fill(['piece_no' => $j + 1, 'unit' => $pc['unit'], 'profile' => $pc['profile']])->save();
                $pieceIds[] = $piece->id;
                if (!empty($pc['measurement_id']) && $piece->measurement?->id == $pc['measurement_id']) {
                    $firstMeasurement ??= $piece->measurement->id;
                    continue;
                }
                if (($pc['unchanged'] ?? false) && $piece->measurement) {
                    $firstMeasurement ??= $piece->measurement->id;
                    continue;
                }
                $values = array_filter($pc['values'], fn($v) => $v !== null && $v !== '');
                if ($values || $piece->measurement) {
                    $attributes = array_fill_keys(Measurement::FIELDS, null);
                    $attributes = array_merge($attributes, array_intersect_key($values, array_flip(Measurement::FIELDS)), [
                        'customer_id' => $order->customer_id, 'order_id' => $order->id, 'piece_no' => $j + 1,
                        'garment_type' => $item->name, 'unit' => $pc['unit'], 'is_template' => false,
                        'details' => array_diff_key($values, array_flip(Measurement::FIELDS)),
                    ]);
                    $measurement = $piece->measurement()->updateOrCreate([], $attributes);
                    $firstMeasurement ??= $measurement->id;
                }
            }
            $item->pieces()->whereNotIn('id', $pieceIds)->delete();
            $json[] = ['name' => $item->name, 'qty' => $item->quantity, 'unit_price' => $item->unit_price, 'price' => $item->subtotal, 'fabric' => $item->fabric, 'style_notes' => $item->style_notes];
        }
        foreach ($order->lineItems()->whereNotIn('id', $kept)->get() as $removed) { $removed->pieces()->delete(); $removed->delete(); }
        $order->forceFill(['items' => $json, 'garment' => $rows[0]['name'], 'product_service_id' => $rows[0]['product_service_id'],
            'fabric' => $rows[0]['fabric'] ?? null, 'measurement_id' => $firstMeasurement, 'items_migrated_at' => now()])->save();
        $order->unsetRelation('lineItems');
    }

    private function fail(string $key, string $message): never
    {
        throw ValidationException::withMessages([$key => $message]);
    }
}
