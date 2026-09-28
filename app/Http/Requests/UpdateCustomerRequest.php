<?php

namespace App\Http\Requests;

use App\Helpers\NationalIdHelper;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCustomerRequest extends FormRequest
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

    /**
     * Nullability mirrors StoreCustomerRequest so a customer created without an
     * email or phone can still be updated, and either field can be cleared.
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'national_id' => array_merge(['sometimes'], $this->nationalIdRules()),
            'address' => ['sometimes', 'nullable', 'string', 'max:500'],
            'notes' => ['sometimes', 'nullable', 'string'],
            'guarantor_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'guarantor_national_id' => array_merge(['sometimes'], $this->nationalIdRules()),
            'guarantor_phone' => ['sometimes', 'nullable', 'string', 'max:50'],
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
