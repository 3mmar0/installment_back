<?php

namespace App\Http\Requests;

use App\Helpers\NationalIdHelper;
use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    protected function prepareForValidation(): void
    {
        $nullable = [
            'email',
            'phone',
            'national_id',
            'address',
            'notes',
            'guarantor_name',
            'guarantor_national_id',
            'guarantor_phone',
        ];

        $payload = [];
        foreach ($nullable as $field) {
            if ($this->exists($field) && is_string($this->input($field)) && trim($this->input($field)) === '') {
                $payload[$field] = null;
            }
        }

        if ($payload !== []) {
            $this->merge($payload);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'national_id' => $this->nationalIdRules(),
            'address' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string'],
            'guarantor_name' => ['nullable', 'string', 'max:255'],
            'guarantor_national_id' => $this->nationalIdRules(),
            'guarantor_phone' => ['nullable', 'string', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'اسم العميل مطلوب.',
            'email.email' => 'صيغة البريد الإلكتروني غير صحيحة.',
        ];
    }

    /**
     * @return array<int, mixed>
     */
    private function nationalIdRules(): array
    {
        return [
            'nullable',
            'string',
            'max:20',
            function (string $attribute, mixed $value, \Closure $fail): void {
                if ($value === null || (is_string($value) && trim($value) === '')) {
                    return;
                }

                if (! NationalIdHelper::isValid((string) $value)) {
                    $fail('الرقم القومي يجب أن يكون 14 رقماً.');
                }
            },
        ];
    }
}
