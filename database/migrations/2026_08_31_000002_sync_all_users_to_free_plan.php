<?php

use App\Enums\UserRole;
use App\Helpers\LimitsHelper;
use App\Models\Subscription;
use App\Models\SubscriptionAssignment;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Subscription::query()->updateOrCreate(
            ['slug' => 'free'],
            [
                'name' => 'الخطه المجانية',
                'currency' => 'EGP',
                'price' => 0,
                'duration' => 'monthly',
                'description' => 'الخطة المجانية الحالية لجميع المستخدمين.',
                'customers' => ['from' => 0, 'to' => 10],
                'installments' => ['from' => 0, 'to' => 20],
                'notifications' => ['from' => 0, 'to' => 200],
                'reports' => true,
                'features' => ['basic_reports' => true],
                'is_active' => true,
                'created_by' => User::query()->value('id'),
            ]
        );

        Subscription::query()
            ->where('slug', '!=', 'free')
            ->update(['is_active' => false]);

        $freePlan = Subscription::query()->where('slug', 'free')->first();

        if (!$freePlan) {
            return;
        }

        User::query()
            ->where('role', UserRole::User)
            ->orderBy('id')
            ->chunkById(100, function ($users) use ($freePlan) {
                foreach ($users as $user) {
                    SubscriptionAssignment::query()
                        ->where('user_id', $user->id)
                        ->where('status', 'active')
                        ->update([
                            'status' => 'canceled',
                            'end_date' => now()->toDateString(),
                        ]);

                    LimitsHelper::applySubscriptionToUser($user->id, $freePlan);
                }
            });
    }

    public function down(): void
    {
        // Irreversible data migration.
    }
};
