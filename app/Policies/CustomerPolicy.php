<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;
use App\Policies\Concerns\ChecksMerchantOwnership;

class CustomerPolicy
{
    use ChecksMerchantOwnership;

    public function view(User $user, Customer $customer): bool
    {
        return $this->owns($user, $customer->user_id);
    }

    public function update(User $user, Customer $customer): bool
    {
        return $this->owns($user, $customer->user_id);
    }

    public function delete(User $user, Customer $customer): bool
    {
        return $this->owns($user, $customer->user_id);
    }
}
