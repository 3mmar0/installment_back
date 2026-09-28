<?php

namespace App\Support;

use App\Helpers\NationalIdHelper;
use App\Helpers\PhoneHelper;
use App\Models\Customer;
use Illuminate\Validation\ValidationException;

class CustomerIdentity
{
    public static function assertAvailable(
        int $userId,
        ?string $nationalId,
        ?string $phone,
        ?int $ignoreCustomerId = null
    ): void {
        $nid = NationalIdHelper::normalize($nationalId);
        $phoneNorm = PhoneHelper::normalize($phone);

        if ($nid !== null) {
            $query = Customer::query()
                ->where('user_id', $userId)
                ->where('national_id', $nid);

            if ($ignoreCustomerId !== null) {
                $query->where('id', '!=', $ignoreCustomerId);
            }

            if ($query->exists()) {
                throw ValidationException::withMessages([
                    'national_id' => 'يوجد عميل مسجّل بنفس الرقم القومي.',
                ]);
            }

            return;
        }

        if ($phoneNorm === null) {
            return;
        }

        $query = Customer::query()
            ->where('user_id', $userId)
            ->where('phone_normalized', $phoneNorm);

        if ($ignoreCustomerId !== null) {
            $query->where('id', '!=', $ignoreCustomerId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'phone' => 'يوجد عميل مسجّل بنفس رقم الهاتف. أدخل الرقم القومي للتمييز أو استخدم العميل الحالي.',
            ]);
        }
    }
}
