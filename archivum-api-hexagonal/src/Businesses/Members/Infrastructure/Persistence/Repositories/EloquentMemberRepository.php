<?php

declare(strict_types=1);

namespace Src\Businesses\Members\Infrastructure\Persistence\Repositories;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Src\Businesses\Members\Domain\Contracts\MemberRepository;
use Src\Businesses\Members\Domain\Entities\Member;
use Src\Businesses\Members\Domain\Exceptions\AccountAlreadyOnStaffException;
use Src\Businesses\Members\Domain\ValueObjects\MemberRole;
use Src\Businesses\Members\Infrastructure\Persistence\Models\MemberModel;
use Src\Shared\Domain\ValueObjects\BusinessId;
use Src\Shared\Domain\ValueObjects\MemberId;
use Src\Shared\Domain\ValueObjects\UserId;

final readonly class EloquentMemberRepository implements MemberRepository
{
    /** Generate a time-ordered UUID for a new member. */
    public function nextId(): MemberId
    {
        return new MemberId((string) Str::uuid7());
    }

    /** Insert the member inside a savepoint; an account already on a staff is refused by members_user_id_unique. */
    public function save(Member $member): void
    {
        try {
            DB::transaction(fn () => MemberModel::query()->updateOrCreate(
                ['id' => $member->id->value, 'business_id' => $member->businessId->value],
                [
                    'business_id' => $member->businessId->value,
                    'user_id' => $member->userId->value,
                    'role' => $member->role->value,
                    'attends_appointments' => $member->attendsAppointments,
                    'is_active' => $member->isActive,
                ],
            ));
        } catch (UniqueConstraintViolationException) {
            throw AccountAlreadyOnStaffException::create();
        }
    }

    /** Find the member only among the rows of the business. */
    public function find(MemberId $id, BusinessId $businessId): ?Member
    {
        $model = MemberModel::query()
            ->where('business_id', $businessId->value)
            ->whereKey($id->value)
            ->first();

        return $model === null ? null : Member::reconstitute(
            new MemberId($model->id),
            new UserId($model->user_id),
            new BusinessId($model->business_id),
            MemberRole::from($model->role),
            $model->attends_appointments,
            $model->is_active,
        );
    }

    /**
     * Lock the rows of the active owners: of two owners removing each other at once, the second waits here, reads the
     * row the first changed and is left with one active owner.
     */
    public function countActiveOwners(BusinessId $businessId): int
    {
        return count(MemberModel::query()
            ->where('business_id', $businessId->value)
            ->where('role', MemberRole::Owner->value)
            ->where('is_active', true)
            ->orderBy('id')
            ->lockForUpdate()
            ->get(['id']));
    }
}
