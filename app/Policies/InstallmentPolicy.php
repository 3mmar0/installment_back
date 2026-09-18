<?php

namespace App\Policies;

use App\Models\Installment;
use App\Models\User;
use App\Policies\Concerns\ChecksMerchantOwnership;

class InstallmentPolicy
{
    use ChecksMerchantOwnership;

    public function view(User $user, Installment $installment): bool
    {
        if ($user->canManageMerchantData()) {
            return true;
        }

        if ($installment->user_id && $user->id === (int) $installment->user_id) {
            return true;
        }

        $customerUserId = $installment->customer?->user_id
            ?? $installment->customer()->value('user_id');

        return $customerUserId !== null && $user->id === (int) $customerUserId;
    }

    public function update(User $user, Installment $installment): bool
    {
        return $this->view($user, $installment);
    }

    public function delete(User $user, Installment $installment): bool
    {
        return $this->view($user, $installment);
    }
}
