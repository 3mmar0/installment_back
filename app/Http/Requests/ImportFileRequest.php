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
        ];
    }
}
