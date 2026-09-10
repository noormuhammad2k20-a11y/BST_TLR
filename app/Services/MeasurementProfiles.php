<?php

namespace App\Services;

use App\Models\Measurement;
use App\Models\ProductService;

final class MeasurementProfiles
{
    public static function infer(string $name, bool $retail = false): string
    {
        if ($retail) return 'accessory';
        $name = strtolower($name);
        foreach (['shalwar' => 'shalwar_kameez', 'sherwani' => 'sherwani', 'waistcoat' => 'waistcoat', 'kurta' => 'kurta_pajama', 'trouser' => 'trouser', 'alteration' => 'alteration'] as $word => $profile) {
            if (str_contains($name, $word)) return $profile;
        }
        return 'generic';
    }

    public static function forProduct(ProductService $product): array
    {
        $key = $product->measurement_profile ?: self::infer($product->name, $product->stock_quantity !== null);
        if ($product->requires_measurements === false || $product->requires_measurements === 0) $key = 'accessory';
        return self::all()[$key] ?? self::all()['generic'];
    }

    public static function all(): array
    {
        $upper = ['length','shoulder_width','sleeve_length','chest','chest_losing','waist','waist_losing','hip','hip_losing','collar','ghera','patti','button','cuff','koni','elbow','armhole','takai'];
        $lower = ['salwar_length','waist','waist_losing','hip','hip_losing','pancho','thigh','knee','rise'];
        $definitions = [
            'shalwar_kameez' => [array_merge($upper, ['salwar_length','pancho']), ['length','shoulder_width','sleeve_length','chest','salwar_length','pancho']],
            'sherwani' => [$upper, ['length','shoulder_width','sleeve_length','chest','waist']],
            'trouser' => [$lower, ['salwar_length','waist','hip','pancho']],
            'waistcoat' => [['length','shoulder_width','chest','chest_losing','waist','waist_losing','hip','hip_losing','collar','armhole','button'], ['length','shoulder_width','chest','waist']],
            'kurta_pajama' => [array_merge($upper, $lower), ['length','shoulder_width','sleeve_length','chest','salwar_length','pancho']],
            'generic' => [Measurement::FIELDS, ['length','chest','waist']],
            'alteration' => [array_merge($upper, $lower), []],
            'accessory' => [[], []],
        ];
        $result = [];
        foreach ($definitions as $key => [$fields, $required]) {
            $fields = array_values(array_unique($fields));
            $labels = array_combine($fields, array_map([Measurement::class, 'label'], $fields));
            if (in_array($key, ['trouser','kurta_pajama'])) {
                $labels['salwar_length'] = $key === 'trouser' ? 'Trouser length' : 'Pajama length';
                $labels['pancho'] = 'Bottom';
            }
            $result[$key] = ['key' => $key, 'fields' => $fields, 'labels' => $labels,
                'required' => array_values(array_unique(array_merge($required, array_intersect(Settings::requiredMeasurementFields(), $fields)))),
                'at_least_one' => $key === 'alteration'];
        }
        return $result;
    }
}
