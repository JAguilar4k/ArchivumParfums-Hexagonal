<?php

declare(strict_types=1);

namespace Src\Businesses\Members\Infrastructure\Persistence\Queries;

use Illuminate\Database\Eloquent\Builder;
use Src\Businesses\Members\Application\Contracts\MemberDirectory;
use Src\Businesses\Members\Application\DTOs\MemberProfile;
use Src\Businesses\Members\Domain\ValueObjects\MemberRole;
use Src\Businesses\Members\Infrastructure\Persistence\Models\MemberModel;
use Src\Shared\Domain\ValueObjects\BusinessId;
use Src\Shared\Domain\ValueObjects\MemberId;

final readonly class EloquentMemberDirectory implements MemberDirectory
{
    /**
     * List the staff of the business by name.
     *
     * @return list<MemberProfile>
     */
    public function all(BusinessId $businessId): array
    {
        return array_values(array_map(
            $this->toProfile(...),
            $this->staffOf($businessId)->orderBy('users.name')->get()->all(),
        ));
    }

    /** Find one person among the staff of the business. */
    public function find(MemberId $id, BusinessId $businessId): ?MemberProfile
    {
        $row = $this->staffOf($businessId)->where('members.id', $id->value)->first();

        return $row === null ? null : $this->toProfile($row);
    }

    /**
     * Members of the business with the name and the email of their account.
     *
     * @return Builder<MemberModel>
     */
    private function staffOf(BusinessId $businessId): Builder
    {
        return MemberModel::query()
            ->join('users', 'users.id', '=', 'members.user_id')
            ->where('members.business_id', $businessId->value)
            ->select(['members.*', 'users.name as account_name', 'users.email as account_email']);
    }

    /** Map a row of the staff to what the panel shows. */
    private function toProfile(MemberModel $row): MemberProfile
    {
        return new MemberProfile(
            $row->id,
            (string) $row->getAttribute('account_name'),
            (string) $row->getAttribute('account_email'),
            MemberRole::from($row->role),
            $row->attends_appointments,
            $row->is_active,
        );
    }
}
