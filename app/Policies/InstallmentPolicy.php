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
        return $this->owns($user, $installment->user_id);
    }

    public function update(User $user, Installment $installment): bool
    {
        return $this->owns($user, $installment->user_id);
    }

    public function delete(User $user, Installment $installment): bool
    {
        return $this->owns($user, $installment->user_id);
    }
}
