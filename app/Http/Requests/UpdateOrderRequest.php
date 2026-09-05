<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'product_service_id' => ['nullable', 'integer', 'exists:product_services,id'],
            'staff_id'           => ['nullable', 'integer', 'exists:staff,id'],
            'garment'            => ['nullable', 'string', 'max:255'],
            'fabric'             => ['nullable', 'string', 'max:255'],
            'style_notes'        => ['nullable', 'string', 'max:2000'],
            'total'              => ['required', 'numeric', 'min:0', 'max:99999999'],
            'advance'            => ['required', 'numeric', 'min:0', 'lte:total'],
            'status'             => ['required', Rule::in(\App\Models\Order::ALL_STATUSES)],
            'priority'           => ['required', Rule::in(['Normal', 'High', 'Express'])],
            'delivery_date'      => ['nullable', 'date'],
            'time_slot'          => ['nullable', 'string', 'max:100'],
            'notes'              => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'advance.lte'     => 'The advance cannot be more than the total amount.',
            'status.required' => 'Please choose an order status.',
        ];
    }
}
