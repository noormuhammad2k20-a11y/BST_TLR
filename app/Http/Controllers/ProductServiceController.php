<?php

namespace App\Http\Controllers;

use App\Models\ProductService;
use App\Services\ActivityLogger;
use App\Services\NotificationService;
use App\Services\StatsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductServiceController extends Controller
{
    public const CATEGORIES = ['Suit', 'Shirt', 'Men', 'Ladies', 'Service', 'Formal', 'Uniform', 'Alteration', 'Fabric', 'Other'];

    public function index()
    {
        $services = ProductService::query()
            ->withCount('orders')
            ->orderBy('name')
            ->get()
            ->map(fn (ProductService $s) => $this->serialize($s));

        $categories = self::CATEGORIES;

        return view('products-services.index', compact('services', 'categories'));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validated($request);

        $service = ProductService::create($validated);

        ActivityLogger::created(
            $service,
            sprintf('%s added to the catalogue', $service->name),
            'inventory'
        );

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

        // Orders reference this record; deactivate rather than orphan history.
        if ($service->orders()->withTrashed()->exists() || $service->lineItems()->withTrashed()->exists() || ProductService::where('canonical_id', $service->id)->exists()) {
            return response()->json([
                'message' => 'This item is used by existing orders. Set it to Inactive instead.',
            ], 422);
        }

        $name = $service->name;
        $service->delete();

        ActivityLogger::log(
            'Deleted ProductService',
            sprintf('%s removed from the catalogue', $name),
            'inventory',
            null,
            [],
            'deleted'
        );

        StatsService::flush();

        return response()->json(['message' => 'Service deleted successfully.']);
    }

    /* ------------------------------------------------------------------ */

    private function validated(Request $request, ?ProductService $service = null): array
    {
        $request->merge(['normalized_name' => \App\Services\CatalogueIdentity::normalize((string)$request->input('name'))]);
        $validated = $request->validate([
            'normalized_name' => ['required', Rule::unique('product_services', 'normalized_name')->ignore($service?->id)],
            'measurement_profile' => ['nullable', Rule::in(array_keys(\App\Services\MeasurementProfiles::all()))],
            'requires_measurements' => ['nullable','boolean'],
            'name'                => [
                'required', 'string', 'max:255',
                Rule::unique('product_services')->ignore($service?->id),
            ],
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
        ], [
            'name.unique'      => 'An item with this name already exists.',
            'category.in'      => 'Please choose a valid category.',
        ]);

        // Fabrics and accessories carry stock; services do not.
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
        // Warn once when a stocked item crosses its threshold.
        if ($service->is_low_stock) {
            NotificationService::lowStock($service->name, (int) $service->stock_quantity);
        }

        StatsService::flush();
    }
}
