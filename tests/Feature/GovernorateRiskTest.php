<?php

use App\Models\Customer;
use App\Services\CreditScore\InstallmentAffordabilityService;

test('distant customer governorate appears as installment location risk', function () {
    $vendor = merchantWithPlan();
    $vendor->forceFill(['governorate' => 'cairo'])->save();
    $customer = Customer::factory()->forMerchant($vendor)->create([
        'governorate' => 'aswan',
    ]);

    $assessment = app(InstallmentAffordabilityService::class)->assess($customer);

    expect($assessment['location_risk'])->toBe('distant')
        ->and($assessment['customer_governorate'])->toBe('aswan')
        ->and($assessment['vendor_governorate'])->toBe('cairo')
        ->and($assessment['reasons'])->toContain(
            'العميل في أسوان وأنت في القاهرة — بعيد جداً، يرتفع خطر التحصيل والمتابعة.'
        );
});

test('same governorate is reported as low geographic risk', function () {
    $vendor = merchantWithPlan();
    $vendor->forceFill(['governorate' => 'cairo'])->save();
    $customer = Customer::factory()->forMerchant($vendor)->create([
        'governorate' => 'cairo',
    ]);

    $assessment = app(InstallmentAffordabilityService::class)->assess($customer);

    expect($assessment['location_risk'])->toBe('same')
        ->and($assessment['reasons'])->toContain('العميل في نفس محافظتك — مخاطر جغرافية منخفضة.');
});
