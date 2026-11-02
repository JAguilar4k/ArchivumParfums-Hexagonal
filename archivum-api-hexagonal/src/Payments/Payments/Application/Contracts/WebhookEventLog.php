<?php

declare(strict_types=1);

namespace Src\Payments\Payments\Application\Contracts;

use Src\Payments\Payments\Application\DTOs\RegisteredPaymentEvent;
use Src\Payments\Payments\Domain\ValueObjects\PaymentEvent;

interface WebhookEventLog
{
    /**
     * Keep a verified notice, pending, before it is processed. Only the first arrival of an event, by the provider's
     * identifier (gateway, event_id), is kept: answer the identifier of its record, or null for a repeated arrival,
     * which changes nothing.
     */
    public function register(PaymentEvent $event, string $payload): ?string;

    /**
     * Lock the record of a notice until the transaction ends and answer it, or null when it is already processed.
     * Two attempts to process the same notice wait for each other, and the later one finds it processed.
     */
    public function lockForProcessing(string $id): ?RegisteredPaymentEvent;

    /** Mark the notice processed now, in the transaction that applied it. */
    public function markProcessed(string $id): void;

    /** Mark the notice failed with the translation key of its error, unless it is already processed. */
    public function markFailed(string $id, string $error): void;
}
