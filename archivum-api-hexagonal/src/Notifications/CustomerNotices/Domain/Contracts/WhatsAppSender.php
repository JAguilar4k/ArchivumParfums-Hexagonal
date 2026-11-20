<?php

declare(strict_types=1);

namespace Src\Notifications\CustomerNotices\Domain\Contracts;

interface WhatsAppSender
{
    public function send(string $phone, string $message): void;
}
