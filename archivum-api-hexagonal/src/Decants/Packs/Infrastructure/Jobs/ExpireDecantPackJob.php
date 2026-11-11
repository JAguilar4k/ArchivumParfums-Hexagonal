<?php

declare(strict_types=1);

namespace Src\Decants\Packs\Infrastructure\Jobs;

use Src\Decants\Packs\Application\DTOs\ExpireDecantPackCommand;
use Src\Decants\Packs\Application\UseCases\ExpireDecantPack;
use Src\Decants\Packs\Domain\ValueObjects\ExpiryReason;
use Src\Shared\Infrastructure\Queue\IdempotentJob;
use Src\Shared\Infrastructure\Queue\ScopeJobToBusiness;

/** Runs when the payment deadline or the validity of a pack ends, and expires it if it still has the status of that reason (#159). */
final class ExpireDecantPackJob extends IdempotentJob
{
    /** Carry the pack, its business and the reason of its expiry. */
    public function __construct(
        public readonly string $packId,
        public readonly string $businessId,
        public readonly ExpiryReason $reason,
    ) {}

    /** One copy per pack and reason waits in the queue: the job of the validity does not wait for the one of the payment. */
    public function uniqueId(): string
    {
        return $this->packId.':'.$this->reason->value;
    }

    /**
     * Run in one transaction of the business of the pack: a worker has no request to take the business from.
     *
     * @return list<object>
     */
    public function middleware(): array
    {
        return [new ScopeJobToBusiness($this->businessId)];
    }

    /** Expire the pack if it still has the status of its reason; the use case is resolved after the middleware binds the business. */
    public function handle(ExpireDecantPack $expireDecantPack): void
    {
        $expireDecantPack->execute(new ExpireDecantPackCommand($this->packId, $this->reason->value, now()->toIso8601String()));
    }
}
