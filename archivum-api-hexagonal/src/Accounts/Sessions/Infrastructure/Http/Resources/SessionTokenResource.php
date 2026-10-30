<?php

declare(strict_types=1);

namespace Src\Accounts\Sessions\Infrastructure\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Accounts\Sessions\Application\DTOs\StartedSession;

/**
 * @property-read StartedSession $resource
 */
final class SessionTokenResource extends JsonResource
{
    private const string TYPE = 'Bearer';

    /** Wrap the token issued at sign-in or at a renewal. */
    public function __construct(private readonly StartedSession $session)
    {
        parent::__construct($session);
    }

    /**
     * Expose the token, the way to send it and when it expires.
     *
     * @return array<string, string>
     */
    public function toArray(Request $request): array
    {
        return [
            'token' => $this->session->plainTextToken,
            'tipo' => self::TYPE,
            'vence_en' => $this->session->expiresAt,
        ];
    }
}
