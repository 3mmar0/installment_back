<?php

namespace App\Services;

use App\Exceptions\PaymentException;
use App\Models\Installment;
use App\Models\InstallmentItem;
use Carbon\Carbon;

class InstallmentPaymentApplier
{
    /**
     * Apply a payment to a locked installment item.
     * A short payment closes this month and moves the remainder to the next month
     * (or creates a new month when this is the last item).
     */
    public function apply(
        InstallmentItem $locked,
        float $paidAmount,
        ?string $reference,
        ?string $note
    ): InstallmentItem {
        if ($locked->status === 'paid') {
            throw PaymentException::alreadyPaid();
        }

        $paidAmount = round($paidAmount, 2);
        $scheduled = round((float) $locked->amount, 2);

        if ($paidAmount > $scheduled + 0.001) {
            throw PaymentException::amountMismatch($scheduled);
        }

        $remainder = round($scheduled - $paidAmount, 2);
        $paymentNote = $this->mergeNote($note, null);

        if ($remainder > 0.001) {
            $this->carryRemainder($locked, $remainder);
            $carryNote = sprintf(
                'تم سداد %s ج.م وترحيل %s ج.م للشهر التالي.',
                number_format($paidAmount, 2),
                number_format($remainder, 2)
            );
            $paymentNote = $this->mergeNote($note, $carryNote);
        }

        $locked->update([
            'amount' => $paidAmount,
            'paid_at' => now(),
            'paid_amount' => $paidAmount,
            'status' => 'paid',
            'reference' => $reference,
            'note' => $paymentNote,
        ]);

        $installment = $locked->installment;
        if ($installment && $installment->items()->where('status', '!=', 'paid')->count() === 0) {
            $installment->update(['status' => 'completed']);
        }

        return $locked->refresh();
    }

    private function carryRemainder(InstallmentItem $locked, float $remainder): void
    {
        $next = InstallmentItem::query()
            ->where('installment_id', $locked->installment_id)
            ->where('id', '!=', $locked->id)
            ->where('status', '!=', 'paid')
            ->where(function ($query) use ($locked) {
                $query->where('due_date', '>', $locked->due_date)
                    ->orWhere(function ($sameDay) use ($locked) {
                        $sameDay->whereDate('due_date', $locked->due_date)
                            ->where('id', '>', $locked->id);
                    });
            })
            ->orderBy('due_date')
            ->orderBy('id')
            ->lockForUpdate()
            ->first();

        $carryNote = sprintf(
            'يشمل %s ج.م مرحّلة من قسط سابق.',
            number_format($remainder, 2)
        );

        if ($next) {
            $next->update([
                'amount' => round((float) $next->amount + $remainder, 2),
                'note' => $this->mergeNote($next->note, $carryNote),
            ]);

            return;
        }

        $due = Carbon::parse($locked->due_date)->startOfDay()->addMonth();
        InstallmentItem::create([
            'installment_id' => $locked->installment_id,
            'due_date' => $due->toDateString(),
            'amount' => $remainder,
            'status' => 'pending',
            'note' => $carryNote,
        ]);

        $installment = Installment::query()
            ->whereKey($locked->installment_id)
            ->lockForUpdate()
            ->first();

        if ($installment) {
            $installment->update([
                'months' => $installment->items()->count(),
                'end_date' => $due->toDateString(),
            ]);
        }
    }

    private function mergeNote(?string $existing, ?string $addition): ?string
    {
        $parts = array_values(array_filter([
            is_string($existing) ? trim($existing) : '',
            is_string($addition) ? trim($addition) : '',
        ]));

        if ($parts === []) {
            return null;
        }

        return mb_substr(implode(' ', $parts), 0, 2000);
    }
}
