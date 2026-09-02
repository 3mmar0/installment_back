<?php

namespace App\Policies;

use App\Models\InstallmentItem;
use App\Models\User;
use App\Policies\Concerns\ChecksMerchantOwnership;

class InstallmentItemPolicy
{
    use ChecksMerchantOwnership;

    public function update(User $user, InstallmentItem $item): bool
    {
        $item->loadMissing('installment');

        return $this->owns($user, $item->installment->user_id);
    }
}
