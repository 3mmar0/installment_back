<?php

namespace App\Services\CreditScore;

use App\Enums\PaymentRequestStatus;
use App\Models\Customer;
use App\Models\Installment;
use App\Models\PaymentRequest;
use App\Services\CreditScore\Support\CreditContract;
use App\Services\CreditScore\Support\CreditItemRecord;
use App\Services\CreditScore\Support\CreditScoreConfig;
use App\Services\CreditScore\Support\CustomerCreditData;
use Carbon\CarbonImmutable;

/**
 * Loads all installment data for one customer in a handful of queries (no N+1)
 * and builds the immutable input object for the scoring engine.
 */
class CustomerCreditDataCollector
{
    public function collect(Customer $customer, ?CreditScoreConfig $config = null): CustomerCreditData
    {
        $config ??= CreditScoreConfig::fromLiveConfig();
        $today = CarbonImmutable::now()->startOfDay();
        $calculator = new DelinquencyCalculator($config);

        $installments = Installment::query()
            ->where('customer_id', $customer->id)
            ->with(['items' => fn ($q) => $q->orderBy('due_date')])
            ->orderBy('start_date')
            ->get();

        $itemIds = $installments->flatMap(fn (Installment $i) => $i->items->pluck('id'))->all();

        $paidOnByItem = $this->approvedPaidOnByItem($itemIds);

        $records = [];
        foreach ($installments as $installment) {
            foreach ($installment->items as $item) {
                if ($item->due_date === null) {
                    continue;
                }

                $isPaid = $item->status === 'paid' || $item->paid_at !== null;
                $closure = null;
                if ($isPaid) {
                    if (isset($paidOnByItem[$item->id])) {
                        $closure = $this->parseDateStartOfDay($paidOnByItem[$item->id]);
                    } elseif ($item->paid_at !== null) {
                        $closure = $this->parseDateStartOfDay($item->paid_at);
                    }
                }

                $dueDate = $this->parseDateStartOfDay($item->due_date);
                if ($dueDate === null) {
                    continue;
                }

                $scored = $calculator->analyze([
                    'due_date' => $dueDate,
                    'amount' => (float) $item->amount,
                    'is_paid' => $isPaid,
                    'closure_date' => $closure,
                ], $today);

                $installmentStart = $installment->start_date !== null
                    ? $this->parseDateStartOfDay($installment->start_date)
                    : $dueDate;

                $records[] = new CreditItemRecord(
                    scored: $scored,
                    amount: (float) $item->amount,
                    isPaid: $isPaid,
                    installmentStatus: (string) $installment->status,
                    installmentStart: $installmentStart ?? $dueDate,
                );
            }
        }

        $contracts = [];
        foreach ($installments as $installment) {
            $totalItems = $installment->items->count();
            $paidItems = $installment->items->filter(
                fn ($item) => $item->status === 'paid' || $item->paid_at !== null
            )->count();

            $contracts[] = new CreditContract(
                id: (int) $installment->id,
                status: (string) $installment->status,
                totalAmount: (float) $installment->total_amount,
                startDate: $installment->start_date !== null
                    ? $this->parseDateStartOfDay($installment->start_date)
                    : null,
                createdAt: $installment->created_at !== null
                    ? $this->parseDateStartOfDay($installment->created_at)
                    : null,
                totalItems: $totalItems,
                paidItems: $paidItems,
            );
        }

        return new CustomerCreditData(
            customerId: (int) $customer->id,
            userId: $customer->user_id !== null ? (int) $customer->user_id : null,
            customerCreatedAt: $customer->created_at !== null
                ? $this->parseDateStartOfDay($customer->created_at)
                : null,
            nationalId: $customer->national_id,
            phone: $customer->phone,
            email: $customer->email,
            guarantorPresent: $this->guarantorPresent($customer),
            hasClientAccount: $customer->client_account_id !== null,
            duplicateIdentity: $this->hasDuplicateIdentity($customer),
            monthlySalary: $customer->monthly_salary !== null
                ? (float) $customer->monthly_salary
                : null,
            today: $today,
            records: $records,
            contracts: $contracts,
        );
    }

    /**
     * @param  array<int, int>  $itemIds
     * @return array<int, string>  item_id => paid_on date string
     */
    private function approvedPaidOnByItem(array $itemIds): array
    {
        if ($itemIds === []) {
            return [];
        }

        $rows = PaymentRequest::query()
            ->whereIn('installment_item_id', $itemIds)
            ->where('status', PaymentRequestStatus::Approved)
            ->orderByDesc('reviewed_at')
            ->get(['installment_item_id', 'paid_on']);

        $map = [];
        foreach ($rows as $row) {
            $id = (int) $row->installment_item_id;
            if (! isset($map[$id]) && $row->paid_on !== null) {
                $map[$id] = $row->paid_on instanceof \DateTimeInterface
                    ? $row->paid_on->format('Y-m-d')
                    : (string) $row->paid_on;
            }
        }

        return $map;
    }

    private function guarantorPresent(Customer $customer): bool
    {
        return trim((string) ($customer->guarantor_name ?? '')) !== ''
            || trim((string) ($customer->guarantor_phone ?? '')) !== ''
            || trim((string) ($customer->guarantor_national_id ?? '')) !== '';
    }

    private function hasDuplicateIdentity(Customer $customer): bool
    {
        if ($customer->user_id === null) {
            return false;
        }

        $base = Customer::query()
            ->where('user_id', $customer->user_id)
            ->where('id', '!=', $customer->id);

        $nationalId = trim((string) ($customer->national_id ?? ''));
        if ($nationalId !== '') {
            if ((clone $base)->where('national_id', $nationalId)->exists()) {
                return true;
            }
        }

        $phoneNorm = trim((string) ($customer->phone_normalized ?? ''));
        if ($phoneNorm !== '') {
            if ((clone $base)->where('phone_normalized', $phoneNorm)->exists()) {
                return true;
            }
        }

        return false;
    }

    private function parseDateStartOfDay(mixed $value): ?CarbonImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}
