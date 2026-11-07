<?php

declare(strict_types=1);

namespace Src\Payments\Payments\Application\Contracts;

use Src\Payments\Payments\Domain\ValueObjects\PaymentEvent;

interface WebhookEventLog
{
    /**
     * Keep a verified notice with its outcome: processed, or failed with the translation key of its error. A notice the
     * provider sends again updates its outcome, but an outcome already processed stays processed.
     */
    public function record(PaymentEvent $event, string $payload, ?string $error = null): void;
}
