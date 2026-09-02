<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

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
            'customer_id' => ['required', $this->customerBelongsToCaller()],
            'name' => ['nullable', 'string', 'max:255'],
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

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'customer_id.exists' => 'العميل غير موجود أو غير مصرح لك باستخدامه.',
        ];
    }

    /**
     * Restrict the customer to one the caller owns.
     *
     * Existence alone is not enough: without the user_id constraint any merchant can
     * attach an installment to another merchant's customer, because the service
     * stamps user_id from the authenticated user rather than from the customer.
     */
    protected function customerBelongsToCaller(): Exists
    {
        $user = $this->user();

        return Rule::exists('customers', 'id')->where(function ($query) use ($user) {
            // Owners administer every merchant, so they are not tenant-scoped.
            if ($user && ! $user->isOwner()) {
                $query->where('user_id', $user->id);
            }
        });
    }
}
