<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AdjustPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'new_paid_amount' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'new_paid_amount.required' => 'المبلغ المدفوع الجديد مطلوب.',
            'new_paid_amount.min' => 'المبلغ المدفوع لا يمكن أن يكون سالباً.',
        ];
    }
}
