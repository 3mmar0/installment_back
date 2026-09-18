<?php

use App\Models\Customer;
use App\Models\Installment;
use App\Models\InstallmentItem;
use App\Services\InstallmentService;

it('merges overdue dashboard payments for the same installment into one row', function () {
    $merchant = actingAsMerchant();
    $customer = Customer::factory()->forMerchant($merchant)->create(['name' => 'عميل متأخر']);
    $installment = Installment::factory()->forCustomer($customer)->create([
        'user_id' => $merchant->id,
        'status' => 'active',
        'total_amount' => 600,
        'months' => 3,
    ]);

    InstallmentItem::factory()->forInstallment($installment)->overdue()->create([
        'amount' => 100,
        'due_date' => now()->subDays(40)->toDateString(),
    ]);
    InstallmentItem::factory()->forInstallment($installment)->overdue()->create([
        'amount' => 200,
        'due_date' => now()->subDays(10)->toDateString(),
    ]);
    InstallmentItem::factory()->forInstallment($installment)->overdue()->create([
        'amount' => 300,
        'due_date' => now()->subDays(5)->toDateString(),
    ]);

    $other = Installment::factory()->forCustomer($customer)->create([
        'user_id' => $merchant->id,
        'status' => 'active',
    ]);
    InstallmentItem::factory()->forInstallment($other)->overdue()->create(['amount' => 50]);

    $rows = app(InstallmentService::class)->overduePaymentsForDashboard($merchant);
    $grouped = $rows->firstWhere('installment_id', $installment->id);

    expect($rows->where('installment_id', $installment->id))->toHaveCount(1)
        ->and($rows)->toHaveCount(2)
        ->and($grouped['count'])->toBe(3)
        ->and($grouped['merged'])->toBeTrue()
        ->and((float) $grouped['amount'])->toBe(600.0)
        ->and((int) $grouped['days_overdue'])->toBeGreaterThanOrEqual(40);
});
