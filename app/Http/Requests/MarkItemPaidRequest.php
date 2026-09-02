<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MarkItemPaidRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * The upper bound is enforced in InstallmentService against the scheduled
     * amount, which is not available here without re-resolving the route model.
     */
    public function rules(): array
    {
        return [
            'paid_amount' => ['required', 'numeric', 'min:0.01', 'max:99999999.99'],
            'note' => ['nullable', 'string', 'max:2000'],
            'reference' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'paid_amount.min' => 'قيمة الدفعة يجب أن تكون أكبر من صفر.',
        ];
    }
}
