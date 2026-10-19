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
                ],
            ));
        } catch (UniqueConstraintViolationException) {
            throw AccountAlreadyOnStaffException::create();
        }
    }

    /**
     * Find the membership of an account by members_user_id_unique. It is the one read without a business,
     * because the business of a person is what it finds; #32 moves it to fn_member_business.
     */
    public function findByUser(UserId $userId): ?Member
    {
        $model = MemberModel::query()->where('user_id', $userId->value)->first();

        return $model === null ? null : Member::reconstitute(
            new MemberId($model->id),
            new UserId($model->user_id),
            new BusinessId($model->business_id),
            MemberRole::from($model->role),
            $model->attends_appointments,
        );
    }
}
