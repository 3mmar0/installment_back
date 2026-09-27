<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportFileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:xlsx', 'max:5120'],
            'type' => ['nullable', 'in:customers,installments'],
            'customer_id' => [
                'nullable',
                'integer',
                'required_if:type,installments',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'يرجى اختيار ملف Excel.',
            'file.mimes' => 'الملف يجب أن يكون بصيغة xlsx.',
            'file.max' => 'حجم الملف يجب ألا يتجاوز 5 ميجابايت.',
            'type.in' => 'نوع الاستيراد غير صالح.',
            'customer_id.required_if' => 'يرجى اختيار العميل الذي ستُضاف له الأقساط.',
        ];
    }
}
