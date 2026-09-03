<?php

namespace App\Services;

use App\Exceptions\PaymentException;
use App\Models\ClientAccount;
use App\Models\Installment;
use App\Models\InstallmentItem;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ClientInstallmentService
{
    /**
     * @return Builder<Installment>
     */
    public function queryForClient(ClientAccount $client): Builder
    {
        $customerIds = $client->customers()->pluck('id');

        return Installment::query()->where(function (Builder $query) use ($customerIds, $client) {
            $query->where('client_account_id', $client->id);

            if ($customerIds->isNotEmpty()) {
                $query->orWhereIn('customer_id', $customerIds);
            }
        });
    }

    public function findForClient(ClientAccount $client, int $id): ?Installment
    {
        return $this->queryForClient($client)
            ->with([
                'user:id,name,email,phone',
                'customer:id,name,email,phone',
                'items.paymentRequests' => function ($q) {
                    $q->where('status', 'pending');
                },
            ])
            ->find($id);
    }

    /**
     * @param  array{
     *     name?: string|null,
     *     total_amount: float|int|string,
     *     months: int,
     *     start_date: string,
     *     products?: array<int, array{name: string, qty: int, price: float|int|string}>,
     *     notes?: string|null
     * }  $data
     */
    public function createPersonal(ClientAccount $client, array $data): Installment
    {
        return DB::transaction(function () use ($client, $data) {
            $start = Carbon::parse($data['start_date'])->startOfDay();
            $months = (int) $data['months'];
            $total = round((float) $data['total_amount'], 2);
            $base = floor(($total / $months) * 100) / 100;
            $remainder = round($total - ($base * $months), 2);

            $installment = Installment::create([
                'user_id' => null,
                'customer_id' => null,
                'client_account_id' => $client->id,
                'name' => ! empty(trim((string) ($data['name'] ?? '')))
                    ? trim((string) $data['name'])
                    : null,
                'total_amount' => $total,
                'products' => $data['products'] ?? [],
                'start_date' => $start->toDateString(),
                'months' => $months,
                'end_date' => $start->copy()->addMonths($months - 1)->toDateString(),
                'status' => 'active',
                'notes' => $data['notes'] ?? null,
            ]);

            for ($i = 0; $i < $months; $i++) {
                $due = $start->copy()->addMonths($i);
                $amount = $base + ($i === ($months - 1) ? $remainder : 0.0);

                InstallmentItem::create([
                    'installment_id' => $installment->id,
                    'due_date' => $due->toDateString(),
                    'amount' => $amount,
                    'status' => 'pending',
                ]);
            }

            return $installment->refresh()->load(['items']);
        });
    }

    /**
     * @param  array{name?: string|null}  $data
     */
    public function updatePersonal(ClientAccount $client, int $id, array $data): Installment
    {
        $installment = $this->findPersonalOrFail($client, $id);

        if (array_key_exists('name', $data)) {
            $installment->name = ! empty(trim((string) $data['name']))
                ? trim((string) $data['name'])
                : null;
        }

        $installment->save();

        return $installment->refresh()->load(['items']);
    }

    public function deletePersonal(ClientAccount $client, int $id): bool
    {
        $installment = $this->findPersonalOrFail($client, $id);

        return (bool) DB::transaction(fn () => $installment->delete());
    }

    /**
     * @param  array{paid_amount?: float|int|string, reference?: string|null, note?: string|null}  $data
     */
    public function markItemPaid(
        ClientAccount $client,
        InstallmentItem $item,
        array $data
    ): InstallmentItem {
        $installment = $item->installment;

        if (! $installment || ! $installment->isPersonal()) {
            abort(403, 'لا يمكن تسجيل الدفع مباشرة لهذا القسط');
        }

        if ((int) $installment->client_account_id !== (int) $client->id) {
            abort(403, 'غير مصرح لك بتسجيل هذه الدفعة');
        }

        $paidAmount = round((float) ($data['paid_amount'] ?? $item->amount), 2);

        return DB::transaction(function () use ($item, $data, $paidAmount, $installment) {
            $locked = InstallmentItem::whereKey($item->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status === 'paid') {
                throw PaymentException::alreadyPaid();
            }

            $scheduled = round((float) $locked->amount, 2);

            if (abs($paidAmount - $scheduled) > 0.001) {
                throw PaymentException::amountMismatch($scheduled);
            }

            $reference = isset($data['reference']) ? trim((string) $data['reference']) : '';
            if ($reference === '') {
                $reference = sprintf('PAY-%d-%s', $locked->getKey(), now()->format('YmdHis'));
            }

            $locked->markPaid(
                $paidAmount,
                $reference,
                isset($data['note']) ? trim((string) $data['note']) : null
            );

            $allPaid = $installment->items()->where('status', '!=', 'paid')->count() === 0;

            if ($allPaid) {
                $installment->update(['status' => 'completed']);
            }

            return $locked->refresh();
        });
    }

    public function customerIdsForClient(ClientAccount $client): Collection
    {
        return $client->customers()->pluck('id');
    }

    private function findPersonalOrFail(ClientAccount $client, int $id): Installment
    {
        $installment = Installment::query()
            ->where('client_account_id', $client->id)
            ->whereNull('user_id')
            ->find($id);

        if (! $installment) {
            abort(404, 'القسط غير موجود');
        }

        return $installment;
    }
}
