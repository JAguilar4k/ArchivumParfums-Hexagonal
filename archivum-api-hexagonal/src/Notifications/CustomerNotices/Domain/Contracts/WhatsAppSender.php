<?php

declare(strict_types=1);

namespace Src\Notifications\CustomerNotices\Domain\Contracts;

use Src\Shared\Domain\ValueObjects\CustomerNotice;

interface WhatsAppSender
{
    public function supports(CustomerNotice $notice): bool;

    /**
     * The message is the Spanish text of lang/es/notices.php; the variables fill an approved template.
     *
     * @param  array<string, string>  $variables
     */
    public function send(CustomerNotice $notice, string $phone, string $message, array $variables): void;
}
