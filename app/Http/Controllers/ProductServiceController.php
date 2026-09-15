<?php

namespace App\Http\Controllers;

use App\Models\ProductService;
use App\Models\TailorCategory;
use App\Models\TailorRate;
use App\Services\ActivityLogger;
use App\Services\NotificationService;
use App\Services\StatsService;
use App\Services\CatalogueIdentity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductServiceController extends Controller
{
    public const CATEGORIES = ['Suit', 'Shirt', 'Men', 'Ladies', 'Service', 'Formal', 'Uniform', 'Alteration', 'Fabric', 'Other'];

    public function index()
    {
        $categories = TailorCategory::with(['rates.service'])->orderBy('name')->get()->map(function ($cat) {
            return [
                'id' => $cat->id,
                'name' => $cat->name,
                'status' => $cat->status,
                'rates' => $cat->rates->map(function ($rate) {
                    $s = $rate->service;
                    return [
                        'id' => $rate->id, // Rate ID
                        'product_service_id' => $s->id,
                        'name' => $s->name,
                        'measurement_profile' => $s->measurement_profile,
                        'requires_measurements' => $s->requires_measurements,
                        'description' => $s->description,
                        'status' => $rate->status,
                        'price' => (float) $rate->price,
                        'orders_count' => \App\Models\Order::where(fn($q) => $q->where('product_service_id',$s->id)->orWhereHas('lineItems',fn($i)=>$i->where('product_service_id',$s->id)))->count(),
                    ];
                })->values()->all(),
            ];
        });

        // Legacy categories for any residual component
        $legacyCategories = self::CATEGORIES;

        return view('products-services.index', compact('categories', 'legacyCategories'));
    }

    public function storeCategory(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('tailor_categories')],
            'status' => ['required', Rule::in(['Active', 'Inactive'])],
        ]);

        $category = TailorCategory::create($validated);
        ActivityLogger::log('Created Stitching Category', "{$category->name} added", 'inventory');
        
        $category->rates = [];
        return response()->json($category);
    }

    public function updateCategory(Request $request, TailorCategory $category): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('tailor_categories')->ignore($category->id)],
            'status' => ['required', Rule::in(['Active', 'Inactive'])],
        ]);

        $category->update($validated);
        ActivityLogger::log('Updated Stitching Category', "{$category->name} updated", 'inventory');
        return response()->json($category);
    }

    public function destroyCategory(TailorCategory $category): JsonResponse
    {
        if ($category->rates()->exists()) {
            return response()->json(['message' => 'Category has rates. Move or delete them first.'], 422);
        }
        $name = $category->name;
        $category->delete();
        ActivityLogger::log('Deleted Stitching Category', "{$name} removed", 'inventory');
        return response()->json(['message' => 'Category deleted.']);
    }

    public function storeRate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category_id' => ['required', 'exists:tailor_categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0'],
            'status' => ['required', Rule::in(['Active', 'Inactive'])],
            'measurement_profile' => ['nullable', 'string'],
            'requires_measurements' => ['boolean'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $service = CatalogueIdentity::seed($validated['name'], [
            'price' => $validated['price'],
            'category' => 'Service',
            'type' => 'Service',
            'status' => 'Active',
            'measurement_profile' => $validated['measurement_profile'] ?? null,
            'requires_measurements' => $validated['requires_measurements'] ?? true,
            'description' => $validated['description'] ?? null,
        ]);

        $rate = TailorRate::create([
            'tailor_category_id' => $validated['category_id'],
            'product_service_id' => $service->id,
            'price' => $validated['price'],
            'status' => $validated['status'],
        ]);

        ActivityLogger::log('Created Stitching Rate', "{$service->name} rate added", 'inventory');
        return response()->json($this->serializeRate($rate));
    }

    public function updateRate(Request $request, TailorRate $rate): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0'],
            'status' => ['required', Rule::in(['Active', 'Inactive'])],
            'measurement_profile' => ['nullable', 'string'],
            'requires_measurements' => ['boolean'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $service = CatalogueIdentity::seed($validated['name'], [
            'price' => $validated['price'],
            'category' => 'Service',
            'type' => 'Service',
            'status' => 'Active',
            'measurement_profile' => $validated['measurement_profile'] ?? null,
            'requires_measurements' => $validated['requires_measurements'] ?? true,
            'description' => $validated['description'] ?? null,
        ]);

        $rate->update([
            'product_service_id' => $service->id,
            'price' => $validated['price'],
            'status' => $validated['status'],
        ]);

        ActivityLogger::log('Updated Stitching Rate', "{$service->name} rate updated", 'inventory');
        return response()->json($this->serializeRate($rate));
    }

    public function destroyRate(TailorRate $rate): JsonResponse
    {
        $name = $rate->service->name;
        $rate->delete();
        ActivityLogger::log('Deleted Stitching Rate', "{$name} rate removed", 'inventory');
        return response()->json(['message' => 'Rate removed.']);
    }

    private function serializeRate(TailorRate $rate): array
    {
        $rate->load('service');
        $s = $rate->service;
        return [
            'id' => $rate->id,
            'product_service_id' => $s->id,
            'name' => $s->name,
            'measurement_profile' => $s->measurement_profile,
            'requires_measurements' => $s->requires_measurements,
            'description' => $s->description,
            'status' => $rate->status,
            'price' => (float) $rate->price,
            'orders_count' => \App\Models\Order::where(fn($q) => $q->where('product_service_id',$s->id)->orWhereHas('lineItems',fn($i)=>$i->where('product_service_id',$s->id)))->count(),
        ];
    }

    /* ------------------------------------------------------------------
       Legacy methods preserved for compatibility
       ------------------------------------------------------------------ */

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validated($request);
        $service = ProductService::create($validated);
        ActivityLogger::created($service, sprintf('%s added to the catalogue', $service->name), 'inventory');
        $this->afterWrite($service);
        return response()->json($this->serialize($service->loadCount('orders')), 201);
    }

    public function update(Request $request, ProductService $productsService): JsonResponse
    {
        $service = $productsService;
        $validated = $this->validated($request, $service);
        $service->update($validated);
        ActivityLogger::updated($service, sprintf('%s updated', $service->name), 'inventory');
        $this->afterWrite($service);
        return response()->json($this->serialize($service->loadCount('orders')));
    }

    public function destroy(ProductService $productsService): JsonResponse
    {
        $service = $productsService;
        if ($service->orders()->withTrashed()->exists() || $service->lineItems()->withTrashed()->exists() || ProductService::where('canonical_id', $service->id)->exists()) {
            return response()->json(['message' => 'This item is used by existing orders. Set it to Inactive instead.'], 422);
        }
        $name = $service->name;
        $service->delete();
        ActivityLogger::log('Deleted ProductService', sprintf('%s removed from the catalogue', $name), 'inventory', null, [], 'deleted');
        StatsService::flush();
        return response()->json(['message' => 'Service deleted successfully.']);
    }

    private function validated(Request $request, ?ProductService $service = null): array
    {
        $request->merge(['normalized_name' => \App\Services\CatalogueIdentity::normalize((string)$request->input('name'))]);
        $validated = $request->validate([
            'normalized_name' => ['required', Rule::unique('product_services', 'normalized_name')->ignore($service?->id)],
            'measurement_profile' => ['nullable', Rule::in(array_keys(\App\Services\MeasurementProfiles::all()))],
            'requires_measurements' => ['nullable','boolean'],
            'name'                => ['required', 'string', 'max:255', Rule::unique('product_services')->ignore($service?->id)],
            'category'            => ['required', Rule::in(self::CATEGORIES)],
            'price'               => ['required', 'numeric', 'min:0', 'max:99999999'],
            'cost_price'          => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'status'              => ['required', Rule::in(['Active', 'Inactive'])],
            'description'         => ['nullable', 'string', 'max:2000'],
            'sku'                 => ['nullable', 'string', 'max:100'],
            'stock_quantity'      => ['nullable', 'integer', 'min:0', 'max:999999'],
            'low_stock_threshold' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'unit'                => ['nullable', 'string', 'max:50'],
            'duration_days'       => ['nullable', 'integer', 'min:0', 'max:365'],
        ], ['name.unique' => 'An item with this name already exists.', 'category.in' => 'Please choose a valid category.']);

        $validated['type'] = match ($validated['category']) {
            'Fabric'  => 'Fabric',
            'Service' => 'Service',
            default   => $request->filled('stock_quantity') ? 'Product' : 'Service',
        };

        return $validated;
    }

    private function serialize(ProductService $s): array
    {
        return [
            'id'                  => $s->id,
            'canonical_id' => $s->canonical_id,
            'measurement_profile' => $s->measurement_profile,
            'requires_measurements' => $s->requires_measurements,
            'name'                => $s->name,
            'sku'                 => $s->sku,
            'category'            => $s->category,
            'type'                => $s->type,
            'price'               => (float) $s->price,
            'cost_price'          => $s->cost_price !== null ? (float) $s->cost_price : null,
            'status'              => $s->status,
            'description'         => $s->description,
            'stock_quantity'      => $s->stock_quantity,
            'low_stock_threshold' => $s->low_stock_threshold,
            'unit'                => $s->unit,
            'duration_days'       => $s->duration_days,
            'orders_count' => \App\Models\Order::where(fn($q) => $q->where('product_service_id',$s->id)->orWhereHas('lineItems',fn($i)=>$i->where('product_service_id',$s->id)))->count(),
            'is_low_stock'        => $s->is_low_stock,
        ];
    }

    private function afterWrite(ProductService $service): void
    {
        if ($service->is_low_stock) NotificationService::lowStock($service->name, (int) $service->stock_quantity);
        StatsService::flush();
    }
}
