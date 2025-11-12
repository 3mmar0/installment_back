<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreUserLimitRequest extends FormRequest
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
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'subscription_name' => ['nullable', 'string', 'max:255'],
            'subscription_slug' => ['nullable', 'string', 'max:255'],
            'currency' => ['nullable', 'string', 'max:10'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'duration' => ['nullable', 'string', 'in:monthly,yearly'],
            'description' => ['nullable', 'string'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'status' => ['nullable', 'string', 'in:active,expired,canceled'],
            'customers' => ['nullable', 'array'],
            'customers.from' => ['nullable', 'integer', 'min:0'],
            'customers.to' => ['nullable', 'integer', 'min:0'],
            'installments' => ['nullable', 'array'],
            'installments.from' => ['nullable', 'integer', 'min:0'],
            'installments.to' => ['nullable', 'integer', 'min:0'],
            'notifications' => ['nullable', 'array'],
            'notifications.from' => ['nullable', 'integer', 'min:0'],
            'notifications.to' => ['nullable', 'integer', 'min:0'],
            'features' => ['nullable', 'array'],
            'reports' => ['nullable', 'boolean'],
        ];
    }
}
