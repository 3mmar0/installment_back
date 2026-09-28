<?php

use App\Helpers\NationalIdHelper;
use App\Helpers\PhoneHelper;
use App\Models\Customer;

it('normalizes a national id to 14 digits', function () {
    expect(NationalIdHelper::normalize('2900101-1234567'))->toBe('29001011234567')
        ->and(NationalIdHelper::normalize('  '))->toBeNull()
        ->and(NationalIdHelper::isValid('29001011234567'))->toBeTrue()
        ->and(NationalIdHelper::isValid('12345'))->toBeFalse();
});

it('stores optional national id and guarantor fields', function () {
    actingAsMerchant();

    $this->postJson('/api/customer-create', [
        'name' => 'أحمد علي',
        'phone' => '01011111111',
        'national_id' => '29001011234567',
        'job' => 'محاسب',
        'guarantor_name' => 'محمد ضامن',
        'guarantor_national_id' => '28501011234567',
        'guarantor_phone' => '01022222222',
    ])->assertCreated()
        ->assertJsonPath('data.national_id', '29001011234567')
        ->assertJsonPath('data.job', 'محاسب')
        ->assertJsonPath('data.guarantor_name', 'محمد ضامن')
        ->assertJsonPath('data.guarantor_national_id', '28501011234567')
        ->assertJsonPath('data.guarantor_phone', '01022222222');
});

it('rejects a duplicate national id for the same merchant', function () {
    $merchant = actingAsMerchant();

    Customer::factory()->forMerchant($merchant)->create([
        'national_id' => '29001011234567',
    ]);

    $this->postJson('/api/customer-create', [
        'name' => 'عميل مكرر',
        'phone' => '01033333333',
        'national_id' => '290-0101-1234567',
    ])->assertStatus(422)
        ->assertJsonValidationErrors('national_id');
});

it('allows the same national id for a different merchant', function () {
    $first = merchantWithPlan();
    Customer::factory()->forMerchant($first)->create([
        'national_id' => '29001011234567',
    ]);

    actingAsMerchant();

    $this->postJson('/api/customer-create', [
        'name' => 'عميل تاجر آخر',
        'national_id' => '29001011234567',
        'phone' => '01044444444',
    ])->assertCreated();
});

it('rejects a duplicate phone when national id is missing', function () {
    $merchant = actingAsMerchant();

    Customer::factory()->forMerchant($merchant)->create([
        'phone' => '01055555555',
        'phone_normalized' => PhoneHelper::normalize('01055555555'),
        'national_id' => null,
    ]);

    $this->postJson('/api/customer-create', [
        'name' => 'عميل بنفس الهاتف',
        'phone' => '01055555555',
    ])->assertStatus(422)
        ->assertJsonValidationErrors('phone');
});

it('allows the same phone when national ids differ', function () {
    $merchant = actingAsMerchant();

    Customer::factory()->forMerchant($merchant)->create([
        'phone' => '01066666666',
        'phone_normalized' => PhoneHelper::normalize('01066666666'),
        'national_id' => '29001011234567',
    ]);

    $this->postJson('/api/customer-create', [
        'name' => 'أخ يستخدم نفس الهاتف',
        'phone' => '01066666666',
        'national_id' => '29101011234567',
    ])->assertCreated();
});

it('rejects an invalid national id', function () {
    actingAsMerchant();

    $this->postJson('/api/customer-create', [
        'name' => 'عميل',
        'national_id' => '12345',
    ])->assertStatus(422)
        ->assertJsonValidationErrors('national_id');
});

it('rejects updating a customer onto another national id', function () {
    $merchant = actingAsMerchant();

    Customer::factory()->forMerchant($merchant)->create([
        'national_id' => '29001011234567',
    ]);

    $other = Customer::factory()->forMerchant($merchant)->create([
        'national_id' => '29101011234567',
        'phone' => '01077777777',
        'phone_normalized' => PhoneHelper::normalize('01077777777'),
    ]);

    $this->putJson("/api/customer-update/{$other->id}", [
        'national_id' => '29001011234567',
    ])->assertStatus(422)
        ->assertJsonValidationErrors('national_id');
});

it('finds a customer by national id in search', function () {
    $merchant = actingAsMerchant();

    Customer::factory()->forMerchant($merchant)->create([
        'name' => 'عميل البحث',
        'national_id' => '29201011234567',
    ]);

    $this->getJson('/api/customer-list?search=29201011234567')
        ->assertOk()
        ->assertJsonFragment(['name' => 'عميل البحث']);
});
