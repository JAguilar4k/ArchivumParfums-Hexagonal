<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Persistence;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Src\Shared\Domain\Contracts\AuditTrail;
use Src\Shared\Domain\ValueObjects\AuditAction;
use Src\Shared\Domain\ValueObjects\BusinessId;
use Src\Shared\Infrastructure\Http\Middleware\AssignRequestId;

final readonly class PostgresAuditTrail implements AuditTrail
{
    /** Fields that never enter the log, wherever a caller puts them. */
    private const array SENSITIVE_FIELDS = ['password', 'contrasena', 'token', 'phone', 'telefono'];

    /**
     * Insert the entry with the account and the request in course.
     *
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public function record(
        BusinessId $businessId,
        AuditAction $action,
        string $resourceType,
        string $resourceId,
        ?array $before,
        ?array $after,
    ): void {
        $actor = Auth::id();
        $requestId = Context::get(AssignRequestId::CONTEXT_KEY);

        DB::table('audit_logs')->insert([
            'business_id' => $businessId->value,
            'actor_user_id' => $actor === null ? null : (string) $actor,
            'action' => $action->value,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'before' => $this->encode($before),
            'after' => $this->encode($after),
            'request_id' => is_string($requestId) ? $requestId : null,
        ]);
    }

    /**
     * The values as JSON, without the sensitive fields.
     *
     * @param  array<string, mixed>|null  $values
     */
    private function encode(?array $values): ?string
    {
        return $values === null ? null : json_encode($this->withoutSensitive($values), JSON_THROW_ON_ERROR);
    }

    /**
     * Remove the sensitive fields at any depth.
     *
     * @param  array<array-key, mixed>  $values
     * @return array<array-key, mixed>
     */
    private function withoutSensitive(array $values): array
    {
        $kept = [];

        foreach ($values as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), self::SENSITIVE_FIELDS, true)) {
                continue;
            }

            $kept[$key] = is_array($value) ? $this->withoutSensitive($value) : $value;
        }

        return $kept;
    }
}
