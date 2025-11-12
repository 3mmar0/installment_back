<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:subscriptions,slug'],
            'currency' => ['nullable', 'string', 'max:10'],
            'price' => ['required', 'numeric', 'min:0'],
            'duration' => ['required', 'in:monthly,yearly'],
            'description' => ['nullable', 'string'],
            'customers' => ['nullable', 'array'],
            'customers.from' => ['nullable', 'integer', 'min:0'],
            'customers.to' => ['nullable', 'integer', 'min:0'],
            'installments' => ['nullable', 'array'],
            'installments.from' => ['nullable', 'integer', 'min:0'],
            'installments.to' => ['nullable', 'integer', 'min:0'],
            'notifications' => ['nullable', 'array'],
            'notifications.from' => ['nullable', 'integer', 'min:0'],
            'notifications.to' => ['nullable', 'integer', 'min:0'],
            'reports' => ['nullable', 'boolean'],
            'features' => ['nullable', 'array'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
