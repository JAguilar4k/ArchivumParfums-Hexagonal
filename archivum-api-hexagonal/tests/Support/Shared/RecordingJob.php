<?php

declare(strict_types=1);

namespace Tests\Support\Shared;

use Illuminate\Support\Facades\DB;
use RuntimeException;
use Src\Shared\Domain\Contracts\BusinessContext;
use Src\Shared\Infrastructure\Queue\IdempotentJob;
use Src\Shared\Infrastructure\Queue\ScopeJobToBusiness;
use Src\Shared\Infrastructure\Tenancy\BusinessTransaction;

/**
 * A job of one business for the tests of the queue (#156). Each attempt records what the job sees, and the first
 * attempts fail as a provider that does not answer would. The worker runs in the process of the test, so the record
 * survives the copies the queue unserializes.
 */
final class RecordingJob extends IdempotentJob
{
    /** @var list<array{tienda: string, fijada: string, transacciones: int, clientes: list<string>}> */
    public static array $attempts = [];

    /** Attempts that fail before one succeeds. */
    public static int $failures = 0;

    /** Carry the business, the key of the work and how ScopeJobToBusiness runs the job. */
    public function __construct(
        public readonly ?string $businessId,
        public readonly string $key = 'recording-job',
        public readonly string $transactionMode = ScopeJobToBusiness::ATOMIC,
    ) {}

    /** Forget the attempts of an earlier test. */
    public static function reset(int $failures = 0): void
    {
        self::$attempts = [];
        self::$failures = $failures;
    }

    public function uniqueId(): string
    {
        return $this->key;
    }

    /** @return list<object> */
    public function middleware(): array
    {
        return [new ScopeJobToBusiness($this->businessId, $this->transactionMode)];
    }

    /**
     * Record the business of the context, the business pinned in the database, the open transactions and the
     * businesses of the customers the job can read, then fail while failures remain.
     */
    public function handle(BusinessContext $context): void
    {
        self::$attempts[] = [
            'tienda' => $context->current()->value,
            'fijada' => (string) DB::scalar('select current_setting(?, true)', [BusinessTransaction::SETTING]),
            'transacciones' => DB::transactionLevel(),
            'clientes' => array_values(array_map(strval(...), DB::table('customers')->distinct()->orderBy('business_id')->pluck('business_id')->all())),
        ];

        if (count(self::$attempts) <= self::$failures) {
            throw new RuntimeException('The provider did not answer.');
        }
    }
}
