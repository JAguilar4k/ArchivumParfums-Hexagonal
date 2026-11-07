<?php

declare(strict_types=1);

namespace Src\Accounts\Users\Infrastructure\Mail;

use Illuminate\Support\Facades\Mail;
use SensitiveParameter;
use Src\Accounts\Users\Domain\Contracts\PasswordResetNotifier;

final readonly class MailPasswordResetNotifier implements PasswordResetNotifier
{
    /**
     * Send the link. Its address comes from config/archivum.php, never from the Host of a request, and it carries only
     * the token: archivum-web asks for the email, so the address of the person stays out of URLs and server logs.
     */
    public function send(string $email, #[SensitiveParameter] string $token): void
    {
        $origin = rtrim(config()->string('archivum.web_origin'), '/');

        Mail::to($email)->send(new PasswordResetMessage("{$origin}/recuperar-contrasena?token=".urlencode($token)));
    }
}
