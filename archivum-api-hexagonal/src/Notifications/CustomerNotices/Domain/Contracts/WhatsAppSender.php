<?php

declare(strict_types=1);

namespace Src\Notifications\CustomerNotices\Domain\Contracts;

use Src\Shared\Domain\ValueObjects\CustomerNotice;

interface WhatsAppSender
{
    public function supports(CustomerNotice $notice): bool;

    public function send(CustomerNotice $notice, string $phone, string $message): void;
}
