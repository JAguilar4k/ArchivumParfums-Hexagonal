<?php

declare(strict_types=1);

namespace Src\Decants\Packs\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Src\Decants\Packs\Application\DTOs\PurchaseDecantPackCommand;
use Src\Decants\Packs\Domain\ValueObjects\IdempotencyKey;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class StorePackPurchase extends FormRequest
{
    public const string IDEMPOTENCY_HEADER = 'Idempotency-Key';

    /**
     * Only the payment method can be chosen: the amount is the price of the stored offer, and any amount sent is ignored.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['medio' => ['sometimes', 'required', 'string', Rule::in(['tarjeta', 'sinpe_movil'])]];
    }

    /**
     * Map the request to the use case input: the account comes from the token, the store from the address, the offer
     * from {paquete} and the key of the purchase from its Idempotency-Key header.
     */
    public function toCommand(): PurchaseDecantPackCommand
    {
        return new PurchaseDecantPackCommand(
            // By name: the route also has {tienda}, which business:route already resolved.
            (string) $this->route('paquete'),
            (string) $this->user()?->getAuthIdentifier(),
            $this->idempotencyKey(),
            $this->validated('medio') === 'sinpe_movil' ? 'sinpe_mobile' : 'card',
            now()->toIso8601String(),
        );
    }

    /**
     * Key of the purchase, from 1 to 100 characters, or none: without it, the purchase cannot be told apart from a
     * retry and is always a new one. A key sent blank or longer is refused, as the guide does.
     */
    private function idempotencyKey(): ?string
    {
        if (! $this->headers->has(self::IDEMPOTENCY_HEADER)) {
            return null;
        }

        $key = trim((string) $this->header(self::IDEMPOTENCY_HEADER, ''));

        if ($key === '' || mb_strlen($key) > IdempotencyKey::MAX_LENGTH) {
            throw new BadRequestHttpException(__('packs.idempotency_key_invalid', ['max' => IdempotencyKey::MAX_LENGTH]));
        }

        return $key;
    }
}
