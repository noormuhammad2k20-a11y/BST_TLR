<?php

namespace App\Http\Requests;

use App\Models\Measurement;
use App\Services\Settings;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $rules = [
            'customer_id'        => ['nullable', 'integer', 'exists:customers,id'],
            'customer_name'      => ['required_without:customer_id', 'nullable', 'string', 'max:255'],
            'customer_phone'     => ['required_without:customer_id', 'nullable', 'string', 'max:50'],
            'product_service_id' => ['nullable', 'integer', 'exists:product_services,id'],
            'measurement_id'     => ['nullable', 'integer', 'exists:measurements,id'],
            'staff_id'           => ['nullable', 'integer', 'exists:staff,id'],
            'garment'            => ['required', 'string', 'max:255'],
            'fabric'             => ['nullable', 'string', 'max:255'],
            'style_notes'        => ['nullable', 'string', 'max:2000'],
            'unit'               => ['nullable', Rule::in(['cm', 'in'])],
            'total'              => ['required', 'numeric', 'min:0.01', 'max:99999999'],
            'advance'            => ['required', 'numeric', 'min:0', 'lte:total'],
            'status'             => ['nullable', Rule::in(\App\Models\Order::ALL_STATUSES)],
            'priority'           => ['nullable', Rule::in(['Normal', 'High', 'Express'])],
            // The browser blocks past dates too, but that is a convenience, not
            // a guarantee — this is the rule that actually holds.
            'delivery_date'      => ['required', 'date', 'after_or_equal:today'],
            'time_slot'          => ['nullable', 'string', 'max:100'],
            'payment_method'     => ['nullable', Rule::in(\App\Models\Payment::METHODS)],
            'notes'              => ['nullable', 'string', 'max:2000'],
            'quantity'           => ['nullable', 'integer', 'min:1', 'max:999'],
            'measurements'       => ['nullable', 'array'],
        ];

        // Mandatory measurements are configured in Settings, so the rule set is
        // rebuilt per request instead of relying on a hardcoded list.
        $required = Settings::requiredMeasurementFields();
        $decimals = Settings::measurementDecimals();

        foreach (Measurement::FIELDS as $field) {
            $rules["measurements.{$field}"] = array_merge(
                [in_array($field, $required, true) ? 'required_with:measurements' : 'nullable'],
                ['numeric', 'min:0', 'max:999', 'decimal:0,' . $decimals]
            );
        }

        return $rules;
    }

    public function messages(): array
    {
        $messages = [
            'garment.required'       => 'Please choose a garment type.',
            'total.min'              => 'The total amount must be greater than zero.',
            'advance.lte'            => 'The advance cannot be more than the total amount.',
            'delivery_date.required'        => 'Please select a delivery date.',
            'delivery_date.after_or_equal'   => 'The delivery date cannot be in the past.',
        ];

        foreach (Settings::requiredMeasurementFields() as $field) {
            $messages["measurements.{$field}.required_with"] = Measurement::label($field) . ' is required.';
        }

        return $messages;
    }
}
