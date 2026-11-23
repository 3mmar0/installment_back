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

        // Overpayment: pay off this item fully, then apply excess to next items
        if ($paidAmount > $remaining + 0.001) {
            $excess = round($paidAmount - $remaining, 2);

            $paymentNote = $this->mergeNote(
                $locked->note,
                $this->mergeNote(
                    $note,
                    sprintf('دفعة %s ج.م.', number_format($paidAmount, 2))
                )
            );

            // Mark current item as fully paid
            $locked->update([
                'paid_at' => now(),
                'paid_amount' => $scheduled,
                'status' => 'paid',
                'reference' => $reference,
                'note' => $this->mergeNote(
                    $paymentNote,
                    sprintf('تم تطبيق %s ج.م على الأقساط التالية.', number_format($excess, 2))
                ),
            ]);

            // Apply excess to subsequent unpaid items
            $this->applyExcessToNextItems($locked, $excess, $reference);

            $installment = $locked->installment;
            if ($installment && $installment->items()->where('status', '!=', 'paid')->count() === 0) {
                $installment->update(['status' => 'completed']);
            }

            return $locked->refresh();
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

    /**
     * Adjust a previously recorded payment to a new amount.
     */
    public function adjust(
        InstallmentItem $locked,
        float $newPaidAmount,
        ?string $note
    ): InstallmentItem {
        $newPaidAmount = round($newPaidAmount, 2);
        $scheduled = round((float) $locked->amount, 2);
        $oldPaid = round((float) ($locked->paid_amount ?? 0), 2);

        if ($newPaidAmount > $scheduled + 0.001) {
            throw PaymentException::amountMismatch($scheduled);
        }

        $adjustNote = sprintf(
            'تعديل الدفعة من %s ج.م إلى %s ج.م.',
            number_format($oldPaid, 2),
            number_format($newPaidAmount, 2)
        );
        $paymentNote = $this->mergeNote($locked->note, $this->mergeNote($note, $adjustNote));

        if ($newPaidAmount <= 0.001) {
            $locked->update([
                'paid_at' => null,
                'paid_amount' => 0,
                'status' => 'pending',
                'note' => $paymentNote,
            ]);
        } elseif ($newPaidAmount >= $scheduled - 0.001) {
            $locked->update([
                'paid_at' => $locked->paid_at ?? now(),
                'paid_amount' => $newPaidAmount,
                'status' => 'paid',
                'note' => $paymentNote,
            ]);
        } else {
            $locked->update([
                'paid_amount' => $newPaidAmount,
                'status' => 'partial',
                'note' => $paymentNote,
            ]);
        }

        $installment = $locked->installment;
        if ($installment) {
            $allPaid = $installment->items()->where('status', '!=', 'paid')->count() === 0;
            $installment->update([
                'status' => $allPaid ? 'completed' : 'active',
            ]);
        }

        return $locked->refresh();
    }

    private function applyExcessToNextItems(InstallmentItem $source, float $excess, ?string $reference): void
    {
        $nextItems = InstallmentItem::query()
            ->where('installment_id', $source->installment_id)
            ->where('id', '!=', $source->id)
            ->where('status', '!=', 'paid')
            ->where(function ($query) use ($source) {
                $query->where('due_date', '>', $source->due_date)
                    ->orWhere(function ($sameDay) use ($source) {
                        $sameDay->whereDate('due_date', $source->due_date)
                            ->where('id', '>', $source->id);
                    });
            })
            ->orderBy('due_date')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $remaining = round($excess, 2);

        foreach ($nextItems as $next) {
            if ($remaining <= 0.001) {
                break;
            }

            $nextScheduled = round((float) $next->amount, 2);
            $nextAlreadyPaid = round((float) ($next->paid_amount ?? 0), 2);
            $nextRemaining = round($nextScheduled - $nextAlreadyPaid, 2);

            if ($nextRemaining <= 0.001) {
                continue;
            }

            $applyToThis = min($remaining, $nextRemaining);
            $newPaid = round($nextAlreadyPaid + $applyToThis, 2);
            $stillOwed = round($nextScheduled - $newPaid, 2);

            $excessNote = sprintf('يشمل %s ج.م من دفعة مقدّمة.', number_format($applyToThis, 2));

            if ($stillOwed <= 0.001) {
                $next->update([
                    'paid_at' => now(),
                    'paid_amount' => $newPaid,
                    'status' => 'paid',
                    'reference' => $reference,
                    'note' => $this->mergeNote($next->note, $excessNote),
                ]);
            } else {
                $next->update([
                    'paid_amount' => $newPaid,
                    'status' => 'partial',
                    'note' => $this->mergeNote($next->note, $excessNote),
                ]);
            }

            $remaining = round($remaining - $applyToThis, 2);
        }
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
