<?php
namespace App\Services;

use App\Models\{Customer, Measurement};
use Illuminate\Support\Facades\DB;

final class MeasurementLibrary
{
    public static function key(Measurement $measurement): string
    {
        return $measurement->customer_id.'|'.mb_strtolower(trim($measurement->garment_type));
    }

    public function save(Customer $customer, array $data): Measurement
    {
        return DB::transaction(function () use ($customer, $data) {
            Customer::whereKey($customer->id)->lockForUpdate()->firstOrFail();
            $data['garment_type'] = trim($data['garment_type']);
            $current = Measurement::savedSets()->where('customer_id', $customer->id)
                ->whereRaw('LOWER(TRIM(garment_type)) = ?', [mb_strtolower($data['garment_type'])])
                ->orderByDesc('updated_at')->orderByDesc('id')->first();
            if ($current) {
                unset($data['created_by']);
                return app(MeasurementEditor::class)->update($current, $data);
            }
            return Measurement::create($data)->load('customer');
        });
    }
}
