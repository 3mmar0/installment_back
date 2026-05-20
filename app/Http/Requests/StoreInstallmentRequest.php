<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreInstallmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'products' => $this->input('products', []),
        ]);
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'exists:customers,id'],
            'total_amount' => ['required', 'numeric', 'min:0.01'],
            'months' => ['required', 'integer', 'min:1', 'max:120'],
            'start_date' => ['required', 'date'],
            'products' => ['nullable', 'array'],
            'products.*.name' => ['required', 'string', 'max:255'],
            'products.*.qty' => ['required', 'integer', 'min:1'],
            'products.*.price' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
