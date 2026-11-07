<?php

declare(strict_types=1);

namespace Src\Accounts\Users\Infrastructure\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

final class PasswordResetMessage extends Mailable
{
    /** Carry the link to archivum-web. */
    public function __construct(public readonly string $link) {}

    /** The subject, from lang/es. */
    public function envelope(): Envelope
    {
        return new Envelope(subject: (string) __('accounts.password_reset_subject'));
    }

    /** The body, from lang/es, with the link. */
    public function content(): Content
    {
        $intro = e((string) __('accounts.password_reset_intro', ['minutes' => config()->integer('auth.passwords.users.expire')]));
        $action = e((string) __('accounts.password_reset_action'));
        $link = e($this->link);

        return new Content(htmlString: "<p>{$intro}</p><p><a href=\"{$link}\">{$action}</a></p>");
    }
}
