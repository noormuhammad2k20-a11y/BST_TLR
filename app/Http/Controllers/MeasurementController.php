<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Measurement;
use App\Models\ProductService;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\Settings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

class MeasurementController extends Controller
{
    public function index()
    {
        $measurementsData = Measurement::with('customer:id,name,phone,code')
            ->latest()
            ->get();

        $customers = Customer::select('id', 'name', 'phone')->orderBy('name')->get();

        $garmentTypes = ProductService::active()
            ->orderBy('name')
            ->pluck('name');

        $tailors = User::tailors()->orderBy('name')->pluck('name');

        $stats = $this->stats($measurementsData);

        // The form reads its unit, decimal precision and mandatory fields from
        // Settings, so changing them there changes the form immediately.
        $measurementConfig = [
            'fields'   => Measurement::FIELDS,
            'labels'   => Measurement::labels(),
            'required' => Settings::requiredMeasurementFields(),
            'unit'     => Settings::measurementUnit(),
            'decimals' => Settings::measurementDecimals(),
            'step'     => Measurement::stepFor(Settings::measurementDecimals()),
        ];

        return view('measurements.index', compact(
            'measurementsData', 'stats', 'customers', 'garmentTypes', 'tailors', 'measurementConfig'
        ));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validated($request);

        $customer = $this->resolveCustomer($validated);

        $data = $this->payload($validated, $customer->id);
        $data['created_by'] = Auth::id();

        $measurement = Measurement::create($data);
        $measurement->load('customer');

        ActivityLogger::created(
            $measurement,
            sprintf('%s measurements recorded for %s', $measurement->garment_type, $customer->name),
            'customers'
        );

        $this->flush();

        return response()->json([
            'success'     => true,
            'message'     => 'Measurement saved successfully.',
            'measurement' => $measurement,
        ], 201);
    }

    public function show(Measurement $measurement): JsonResponse
    {
        $measurement->load('customer:id,name,phone,code');

        return response()->json([
            'success'     => true,
            'measurement' => $measurement,
            'completeness' => $measurement->completeness,
        ]);
    }

    public function update(Request $request, Measurement $measurement): JsonResponse
    {
        if ($measurement->order_item_piece_id) return response()->json(['message'=>'Edit this piece through its order so ownership and completion locks are enforced.'],422);
        $validated = $this->validated($request);

        $customer = $this->resolveCustomer($validated, $measurement);

        $measurement->update($this->payload($validated, $customer->id));
        $measurement->load('customer');

        ActivityLogger::updated(
            $measurement,
            sprintf('%s measurements updated for %s', $measurement->garment_type, $customer->name),
            'customers'
        );

        $this->flush();

        return response()->json([
            'success'     => true,
            'message'     => 'Measurement updated successfully.',
            'measurement' => $measurement,
        ]);
    }

    public function destroy(Measurement $measurement): JsonResponse
    {
        // Keep measurements that orders still point at.
        if ($measurement->order_item_piece_id || $measurement->order_id || $measurement->orders()->withTrashed()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'This measurement is linked to an order and cannot be deleted.',
            ], 422);
        }

        $label = $measurement->garment_type;
        $name  = $measurement->customer?->name ?? 'a customer';

        $measurement->delete();

        ActivityLogger::log(
            'Deleted Measurement',
            sprintf('%s measurement for %s removed', $label, $name),
            'customers',
            null,
            [],
            'deleted'
        );

        $this->flush();

        return response()->json(['success' => true, 'message' => 'Measurement deleted successfully.']);
    }

    /* ------------------------------------------------------------------ */
    /* Helpers                                                             */
    /* ------------------------------------------------------------------ */

    private function validated(Request $request): array
    {
        // Which fields are mandatory is a shop setting, so the rules are built
        // per request rather than baked into a constant.
        $required = Settings::requiredMeasurementFields();
        $decimals = Settings::measurementDecimals();

        $rules = [
            'customer_id'   => ['nullable', 'integer', 'exists:customers,id'],
            'customer_name' => ['required_without:customer_id', 'nullable', 'string', 'max:255'],
            'garment_type'  => ['required', 'string', 'max:255'],
            'tailor'        => ['required', 'string', 'max:255'],
            'unit'          => ['nullable', Rule::in(['cm', 'in'])],
            'notes'         => ['nullable', 'string', 'max:2000'],
        ];

        foreach (Measurement::FIELDS as $field) {
            $rules[$field] = array_merge(
                [in_array($field, $required, true) ? 'required' : 'nullable'],
                ['numeric', 'min:0', 'max:999', 'decimal:0,' . $decimals]
            );
        }

        // Messages name the field the shop sees, whichever set it chose.
        $messages = [
            'garment_type.required' => 'Please choose a garment type.',
            'tailor.required'       => 'Please assign a tailor.',
        ];

        foreach ($required as $field) {
            $messages["{$field}.required"] = Measurement::label($field) . ' is required.';
        }

        foreach (Measurement::FIELDS as $field) {
            $messages["{$field}.decimal"] = Measurement::label($field) . ' allows at most ' . $decimals . ' decimal place(s).';
        }

        $validated = $request->validate($rules, $messages);

        // New records open in the shop's default unit unless one was sent.
        $validated['unit'] = $validated['unit'] ?? Settings::measurementUnit();

        return $validated;
    }

    private function resolveCustomer(array $validated, ?Measurement $existing = null): Customer
    {
        if (!empty($validated['customer_id'])) {
            return Customer::findOrFail($validated['customer_id']);
        }

        $name = trim($validated['customer_name']);

        if ($existing && $existing->customer?->name === $name) {
            return $existing->customer;
        }

        return Customer::firstOrCreate(
            ['name' => $name],
            ['phone' => 'N/A-' . uniqid(), 'type' => 'Regular']
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $validated, int $customerId): array
    {
        $data = collect($validated)
            ->only(array_merge(Measurement::FIELDS, ['garment_type', 'tailor', 'unit', 'notes']))
            ->all();

        $data['customer_id'] = $customerId;

        return $data;
    }

    /**
     * Real figures: template count comes from distinct garment types actually
     * recorded, accuracy from how completely each record is filled in.
     */
    private function stats($measurements): array
    {
        return Cache::remember('measurements.stats', 60, function () use ($measurements) {
            $total = $measurements->count();

            $completeness = $total > 0
                ? round($measurements->avg(fn (Measurement $m) => $m->completeness), 1)
                : 0.0;

            return [
                'total'            => $total,
                'active_templates' => $measurements->pluck('garment_type')->filter()->unique()->count(),
                'recent'           => $measurements->where('created_at', '>=', now()->subDays(30))->count(),
                'accuracy'         => $completeness . '%',
            ];
        });
    }

    private function flush(): void
    {
        Cache::forget('measurements.stats');
    }
}
