<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $subscriptionId = $this->route('subscription')?->id ?? $this->route('subscription');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
                Rule::unique('subscriptions', 'slug')->ignore($subscriptionId),
            ],
            'currency' => ['sometimes', 'nullable', 'string', 'max:10'],
            'price' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'duration' => ['sometimes', 'nullable', 'in:monthly,yearly'],
            'description' => ['sometimes', 'nullable', 'string'],
            'customers' => ['sometimes', 'nullable', 'array'],
            'customers.from' => ['nullable', 'integer', 'min:0'],
            'customers.to' => ['nullable', 'integer', 'min:0'],
            'installments' => ['sometimes', 'nullable', 'array'],
            'installments.from' => ['nullable', 'integer', 'min:0'],
            'installments.to' => ['nullable', 'integer', 'min:0'],
            'notifications' => ['sometimes', 'nullable', 'array'],
            'notifications.from' => ['nullable', 'integer', 'min:0'],
            'notifications.to' => ['nullable', 'integer', 'min:0'],
            'reports' => ['sometimes', 'nullable', 'boolean'],
            'features' => ['sometimes', 'nullable', 'array'],
            'is_active' => ['sometimes', 'nullable', 'boolean'],
        ];
    }
}
