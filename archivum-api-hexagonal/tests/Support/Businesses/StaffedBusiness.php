<?php

declare(strict_types=1);

namespace Tests\Support\Businesses;

use Illuminate\Support\Facades\DB;
use Src\Accounts\Users\Infrastructure\Persistence\Models\CustomerModel;
use Src\Accounts\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Businesses\Members\Infrastructure\Persistence\Models\MemberModel;
use Src\Businesses\Profile\Infrastructure\Persistence\Models\BusinessModel;
use Src\Shared\Infrastructure\Tenancy\BusinessTransaction;

/**
 * A business with one person of each kind: its owner, a member of its staff and one of its customers.
 * The owner and the staff member attend appointments.
 */
final readonly class StaffedBusiness
{
    private function __construct(
        public BusinessModel $business,
        public UserModel $owner,
        public UserModel $staff,
        public UserModel $customer,
    ) {}

    /**
     * Create the business and its three people inside the transaction of the test. The factories pin the
     * transaction to the business of each row they insert, so the business the test had pinned comes back after.
     */
    public static function create(): self
    {
        return DB::transaction(function (): self {
            $previous = DB::scalar('select current_setting(?, true)', [BusinessTransaction::SETTING]);

            try {
                $business = BusinessModel::factory()->create();
                $owner = UserModel::factory()->create();
                $staff = UserModel::factory()->create();
                $customer = UserModel::factory()->create();

                MemberModel::factory()->owner()->create([
                    'business_id' => $business->id,
                    'user_id' => $owner->id,
                    'attends_appointments' => true,
                ]);
                MemberModel::factory()->staff()->create([
                    'business_id' => $business->id,
                    'user_id' => $staff->id,
                    'attends_appointments' => true,
                ]);
                CustomerModel::factory()->create([
                    'business_id' => $business->id,
                    'user_id' => $customer->id,
                ]);

                return new self($business, $owner, $staff, $customer);
            } finally {
                app(BusinessTransaction::class)->scope(is_string($previous) ? $previous : null);
            }
        });
    }
}
