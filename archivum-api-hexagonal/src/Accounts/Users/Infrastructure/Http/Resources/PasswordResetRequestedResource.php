<?php

declare(strict_types=1);

namespace Src\Accounts\Users\Infrastructure\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class PasswordResetRequestedResource extends JsonResource
{
    /**
     * The same body for every request of recovery, whether or not the account exists.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return ['mensaje' => __('accounts.password_reset_requested')];
    }
}
