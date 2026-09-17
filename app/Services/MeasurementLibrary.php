<?php
namespace App\Services;

use App\Models\{Customer, Measurement};
use Illuminate\Support\Facades\DB;

final class MeasurementLibrary
{
    public static function key(Measurement $measurement): string
    {
        return (string) $measurement->customer_id;
    }

    public function save(Customer $customer, array $data): Measurement
    {
        return DB::transaction(function () use ($customer, $data) {
            Customer::whereKey($customer->id)->lockForUpdate()->firstOrFail();
            
            $current = Measurement::savedSets()->where('customer_id', $customer->id)
                ->orderByDesc('updated_at')->orderByDesc('id')->first();
                
            if ($current) {
                unset($data['created_by']);
                unset($data['garment_type']); // Preserve legacy garment_type on update
                return app(MeasurementEditor::class)->update($current, $data);
            }
            
            $data['garment_type'] = trim($data['garment_type'] ?? 'Shared');
            return Measurement::create($data)->load('customer');
        });
    }
}
