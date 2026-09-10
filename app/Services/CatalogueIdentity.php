<?php

namespace App\Services;

use App\Models\ProductService;
use Illuminate\Support\Facades\DB;

final class CatalogueIdentity
{
    public static function normalize(string $name): string
    {
        return mb_strtolower(preg_replace('/\s+/u', ' ', trim($name)));
    }

    public static function reconcile(bool $apply = false): array
    {
        return DB::transaction(function () use ($apply) {
            $groups = ProductService::orderBy('id')->get()->each(function($product) {
                $product->orders_count = \App\Models\Order::withTrashed()->where(fn($q) => $q->where('product_service_id',$product->id)
                    ->orWhereHas('lineItems', fn($items) => $items->withTrashed()->where('product_service_id',$product->id)))->count();
            })->groupBy(fn($p) => self::normalize($p->name));
            $report = [];
            foreach ($groups as $key => $products) {
                $products = $products->sort(fn($a,$b) => ($b->orders_count <=> $a->orders_count) ?: ($a->id <=> $b->id))->values();
                $canonical = $products->first();
                $report[] = ['name' => $key, 'canonical' => $canonical->id, 'aliases' => $products->skip(1)->pluck('id')->all()];
                if (!$apply) continue;
                foreach ($products->skip(1) as $alias) DB::table('product_services')->where('id',$alias->id)->update(['normalized_name' => null, 'canonical_id' => $canonical->id]);
                DB::table('product_services')->where('id',$canonical->id)->update(['normalized_name' => $key, 'canonical_id' => null,
                    'measurement_profile' => $canonical->measurement_profile ?: MeasurementProfiles::infer($canonical->name, $canonical->stock_quantity !== null),
                    'requires_measurements' => $canonical->requires_measurements ?? ($canonical->stock_quantity === null)]);
            }
            return $report;
        });
    }

    public static function seed(string $name, array $attributes): ProductService
    {
        $key = self::normalize($name);
        $existing = ProductService::where('normalized_name',$key)->first()
            ?? ProductService::whereNull('canonical_id')->get()->first(fn($p) => self::normalize($p->name) === $key);
        return $existing ?: ProductService::create(array_merge($attributes, ['name' => $name, 'normalized_name' => $key]));
    }
}
