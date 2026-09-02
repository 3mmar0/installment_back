<?php

namespace App\Services;

use App\Helpers\PhoneHelper;
use App\Models\ClientAccount;
use App\Models\Customer;

class ClientLinkService
{
    /**
     * Link all unmatched customer rows that share the client's email or phone.
     *
     * @return int Number of newly linked customer rows
     */
    public function linkForClient(ClientAccount $client): int
    {
        $email = strtolower(trim((string) $client->email));
        $phoneNormalized = $client->phone_normalized;

        $query = Customer::query()
            ->whereNull('client_account_id')
            ->where(function ($builder) use ($email, $phoneNormalized) {
                $builder->whereRaw('LOWER(email) = ?', [$email]);

                if ($phoneNormalized) {
                    $builder->orWhere('phone_normalized', $phoneNormalized);
                }
            });

        $customers = $query->get();

        if ($customers->isEmpty()) {
            return 0;
        }

        $ids = $customers->pluck('id')->all();

        Customer::whereIn('id', $ids)->update([
            'client_account_id' => $client->id,
        ]);

        if (empty($client->name)) {
            $firstName = $customers->first()?->name;
            if ($firstName) {
                $client->forceFill(['name' => $firstName])->save();
            }
        }

        return count($ids);
    }

    /**
     * Inverse link: when a vendor creates/updates a customer, attach an existing client account if matched.
     */
    public function linkForCustomer(Customer $customer): bool
    {
        if ($customer->client_account_id) {
            return false;
        }

        $email = $customer->email ? strtolower(trim($customer->email)) : null;
        $phoneNormalized = $customer->phone_normalized
            ?? PhoneHelper::normalize($customer->phone);

        if (! $email && ! $phoneNormalized) {
            return false;
        }

        $clientQuery = ClientAccount::query()
            ->whereNotNull('email_verified_at')
            ->where(function ($builder) use ($email, $phoneNormalized) {
                if ($email) {
                    $builder->whereRaw('LOWER(email) = ?', [$email]);
                }

                if ($phoneNormalized) {
                    $method = $email ? 'orWhere' : 'where';
                    $builder->{$method}('phone_normalized', $phoneNormalized);
                }
            });

        $client = $clientQuery->first();

        if (! $client) {
            return false;
        }

        $customer->forceFill([
            'client_account_id' => $client->id,
            'phone_normalized' => $phoneNormalized,
        ])->save();

        return true;
    }
}
