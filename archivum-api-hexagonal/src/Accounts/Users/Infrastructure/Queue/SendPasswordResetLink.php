<?php

declare(strict_types=1);

namespace Src\Accounts\Users\Infrastructure\Queue;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Src\Accounts\Users\Application\UseCases\RequestPasswordReset;

final class SendPasswordResetLink implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue;

    /** Carry the email as the person typed it, whether or not it has an account. */
    public function __construct(public readonly string $email) {}

    /** Look for the account, create the token and send the link, all outside the request. */
    public function handle(RequestPasswordReset $requestReset): void
    {
        $requestReset->execute($this->email);
    }
}
