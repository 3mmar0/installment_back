<?php

namespace App\Policies\Concerns;

use App\Models\User;

trait ChecksMerchantOwnership
{
    /**
     * Platform owners may access any merchant's records; merchants may only
     * access records they own.
     */
    protected function owns(User $user, int $resourceUserId): bool
    {
        return $user->isOwner() || $user->id === $resourceUserId;
    }
}
