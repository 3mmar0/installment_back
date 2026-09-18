<?php

namespace App\Policies;

use App\Models\InstallmentItem;
use App\Models\User;

class InstallmentItemPolicy
{
    public function update(User $user, InstallmentItem $item): bool
    {
        $item->loadMissing('installment.customer');

        return $item->installment !== null && $user->can('view', $item->installment);
    }
}
