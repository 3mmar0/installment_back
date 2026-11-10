<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

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
            'assistant_messages' => ['nullable', 'array'],
            'assistant_messages.from' => ['nullable', 'integer', 'min:0'],
            'assistant_messages.to' => ['nullable', 'integer', 'min:0'],
            'whatsapp_messages' => ['nullable', 'array'],
            'whatsapp_messages.from' => ['nullable', 'integer', 'min:0'],
            'whatsapp_messages.to' => ['nullable', 'integer', 'min:0'],
            'reports' => ['nullable', 'boolean'],
            'features' => ['nullable', 'array'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            foreach (['customers', 'installments', 'notifications', 'assistant_messages', 'whatsapp_messages'] as $resource) {
                $range = $this->input($resource);
                if (is_array($range) && array_key_exists('from', $range) && array_key_exists('to', $range) && $range['to'] !== null && (int) $range['from'] > (int) $range['to']) {
                    $validator->errors()->add("{$resource}.to", 'الحد الأقصى يجب أن يكون أكبر من أو مساوياً للحد الأدنى.');
                }
            }
        });
    }
}
