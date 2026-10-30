<?php

declare(strict_types=1);

namespace Src\Accounts\Users\Domain\Contracts;

use Src\Shared\Domain\ValueObjects\UserId;

interface PrivacyConsents
{
    /** Keep that the account accepted a version of the privacy notice, with the current date. */
    public function record(UserId $userId, string $noticeVersion): void;
}
