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
     * Partial payments stay on the same month unless $carryRemainder is true.
     */
    public function apply(
        InstallmentItem $locked,
        float $paidAmount,
        ?string $reference,
        ?string $note,
        bool $carryRemainder = false
    ): InstallmentItem {
        if ($locked->status === 'paid') {
            throw PaymentException::alreadyPaid();
        }

        $paidAmount = round($paidAmount, 2);
        $scheduled = round((float) $locked->amount, 2);
        $alreadyPaid = round((float) ($locked->paid_amount ?? 0), 2);
        $remaining = round($scheduled - $alreadyPaid, 2);

        if ($paidAmount > $remaining + 0.001) {
            throw PaymentException::amountMismatch($remaining);
        }

        $newPaid = round($alreadyPaid + $paidAmount, 2);
        $leftover = round($scheduled - $newPaid, 2);
        $paymentNote = $this->mergeNote(
            $locked->note,
            $this->mergeNote(
                $note,
                $paidAmount > 0.001
                    ? sprintf('دفعة %s ج.م.', number_format($paidAmount, 2))
                    : null
            )
        );

        if ($carryRemainder && $leftover > 0.001) {
            $this->carryRemainder($locked, $leftover);
            $paymentNote = $this->mergeNote(
                $paymentNote,
                sprintf('تم ترحيل %s ج.م للشهر التالي.', number_format($leftover, 2))
            );

            $locked->update([
                'amount' => $newPaid,
                'paid_at' => now(),
                'paid_amount' => $newPaid,
                'status' => 'paid',
                'reference' => $reference,
                'note' => $paymentNote,
            ]);
        } elseif ($leftover <= 0.001) {
            $locked->update([
                'paid_at' => now(),
                'paid_amount' => $newPaid,
                'status' => 'paid',
                'reference' => $reference,
                'note' => $paymentNote,
            ]);
        } else {
            $locked->update([
                'paid_amount' => $newPaid,
                'status' => 'partial',
                'reference' => $reference,
                'note' => $paymentNote,
            ]);
        }

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
