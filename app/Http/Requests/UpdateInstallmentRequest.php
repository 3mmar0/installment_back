<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateInstallmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'name'          => ['sometimes', 'nullable', 'string', 'max:255'],
            'notes'         => ['sometimes', 'nullable', 'string'],
            'invoice_price' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'down_payment'  => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'attachment'    => ['sometimes', 'nullable', 'file', 'max:20480', 'mimes:jpg,jpeg,png,pdf,webp'],
        ];
    }
}
