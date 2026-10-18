<?php

declare(strict_types=1);

namespace Tests\Support\Businesses;

use Illuminate\Support\Facades\DB;
use Src\Accounts\Users\Infrastructure\Persistence\Models\CustomerModel;
use Src\Accounts\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Businesses\Members\Domain\ValueObjects\MemberRole;
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

    /** Create the business and its three people inside the transaction of the test. */
    public static function create(): self
    {
        return DB::transaction(function (): self {
            $business = BusinessModel::factory()->create();
            $owner = UserModel::factory()->create();
            $staff = UserModel::factory()->create();
            $customer = UserModel::factory()->create();
            $previous = DB::scalar('select current_setting(?, true)', [BusinessTransaction::SETTING]);
            $transaction = new BusinessTransaction;

            try {
                return $transaction->run($business->id, function () use ($business, $owner, $staff, $customer): self {
                    MemberModel::query()->create([
                        'business_id' => $business->id,
                        'user_id' => $owner->id,
                        'role' => MemberRole::Owner->value,
                        'attends_appointments' => true,
                    ]);
                    MemberModel::query()->create([
                        'business_id' => $business->id,
                        'user_id' => $staff->id,
                        'role' => MemberRole::Staff->value,
                        'attends_appointments' => true,
                    ]);
                    CustomerModel::query()->create([
                        'business_id' => $business->id,
                        'user_id' => $customer->id,
                    ]);

                    return new self($business, $owner, $staff, $customer);
                });
            } finally {
                $transaction->scope(is_string($previous) ? $previous : null);
            }
        });
    }
}
