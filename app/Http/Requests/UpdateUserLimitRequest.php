<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserLimitRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'subscription_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'subscription_slug' => ['sometimes', 'nullable', 'string', 'max:255'],
            'currency' => ['sometimes', 'nullable', 'string', 'max:10'],
            'price' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'duration' => ['sometimes', 'nullable', 'string', 'in:monthly,yearly'],
            'description' => ['sometimes', 'nullable', 'string'],
            'start_date' => ['sometimes', 'nullable', 'date'],
            'end_date' => ['sometimes', 'nullable', 'date', 'after_or_equal:start_date'],
            'status' => ['sometimes', 'nullable', 'string', 'in:active,expired,canceled'],
            'customers' => ['sometimes', 'nullable', 'array'],
            'customers.from' => ['nullable', 'integer', 'min:0'],
            'customers.to' => ['nullable', 'integer', 'min:0'],
            'installments' => ['sometimes', 'nullable', 'array'],
            'installments.from' => ['nullable', 'integer', 'min:0'],
            'installments.to' => ['nullable', 'integer', 'min:0'],
            'notifications' => ['sometimes', 'nullable', 'array'],
            'notifications.from' => ['nullable', 'integer', 'min:0'],
            'notifications.to' => ['nullable', 'integer', 'min:0'],
            'features' => ['sometimes', 'nullable', 'array'],
            'reports' => ['sometimes', 'nullable', 'boolean'],
        ];
    }
}
