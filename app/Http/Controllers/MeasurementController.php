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
        $measurementsData = Measurement::savedSets()->with('customer:id,name,phone,code')
            ->orderByDesc('updated_at')->orderByDesc('id')
            ->get()->unique(fn ($m) => \App\Services\MeasurementLibrary::key($m))->values();

        $customers = Customer::select('id', 'name', 'phone')->orderBy('name')->get();

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
            'measurementsData', 'stats', 'customers', 'tailors', 'measurementConfig'
        ));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validated($request);

        $customer = $this->resolveCustomer($validated);

        $data = $this->payload($validated, $customer->id);
        $data['created_by'] = Auth::id();

        $measurement = app(\App\Services\MeasurementLibrary::class)->save($customer, $data);
        $measurement->load('customer');

        if ($measurement->wasRecentlyCreated) {
            ActivityLogger::created($measurement, sprintf('Measurements recorded for %s', $customer->name), 'customers');
        } else {
            ActivityLogger::updated($measurement, sprintf('Measurements updated for %s', $customer->name), 'customers');
        }

        $this->flush();

        return response()->json([
            'success'     => true,
            'message'     => 'Measurement saved successfully.',
            'measurement' => $measurement,
        ], $measurement->wasRecentlyCreated ? 201 : 200);
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
        $validated = $this->validated($request);

        $measurement = \Illuminate\Support\Facades\DB::transaction(function () use ($validated, $measurement) {
            $customer = $this->resolveCustomer($validated, $measurement);
            return app(\App\Services\MeasurementLibrary::class)->save(
                $customer, $this->payload($validated, $customer->id)
            );
        });
        $customer = $measurement->customer;

        ActivityLogger::updated(
            $measurement,
            sprintf('Measurements updated for %s', $customer->name),
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

        $label = 'Customer';
        $name  = $measurement->customer?->name ?? 'a customer';

        $measurement->delete();

        ActivityLogger::log(
            'Deleted Measurement',
            sprintf('Measurements for %s removed', $name),
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
            'tailor.required'       => 'Please assign a tailor.',
        ];

        foreach ($required as $field) {
            $messages["{$field}.required"] = Measurement::label($field) . ' is required.';
        }

        foreach (Measurement::FIELDS as $field) {
            $messages["{$field}.decimal"] = Measurement::label($field) . ' allows at most ' . $decimals . ' decimal place(s).';
        }

        $validated = $request->validate($rules, $messages, Measurement::labels());

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
            ->only(array_merge(Measurement::FIELDS, ['tailor', 'unit', 'notes']))
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
        return (function () use ($measurements) {
            $total = $measurements->count();

            $completeness = $total > 0
                ? round($measurements->avg(fn (Measurement $m) => $m->completeness), 1)
                : 0.0;

            return [
                'total'            => $total,
                'active_templates' => $total,
                'recent'           => $measurements->where('updated_at', '>=', now()->subDays(30))->count(),
                'accuracy'         => $completeness . '%',
            ];
        })();
    }

    private function flush(): void
    {
        Cache::forget('measurements.stats');
    }
}
