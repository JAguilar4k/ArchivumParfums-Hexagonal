<?php

declare(strict_types=1);

namespace Src\Payments\Payments\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Src\Payments\Payments\Application\DTOs\RecordManualPaymentCommand;
use Src\Payments\Payments\Domain\ValueObjects\ManualRecord;
use Src\Shared\Domain\ValueObjects\Colones;

final class StoreManualPayment extends FormRequest
{
    /**
     * What the owner received on delivery. The order comes from the URL and the owner from the token.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /** Cómo pagó el cliente al recibir el pedido: `sinpe` o `efectivo`. */
            'metodo' => ['required', 'string', Rule::in(['sinpe', 'efectivo'])],
            /** Colones enteros recibidos. No puede superar el saldo del pedido. */
            'monto' => ['required', 'integer', 'min:1', 'max:'.Colones::MAX],
            /** Referencia de la transferencia SINPE o del recibo del pago en efectivo. */
            'referencia' => ['required', 'string', 'max:'.ManualRecord::MAX_REFERENCE_LENGTH],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['metodo' => 'método'];
    }

    /** Map the request to the use case: the order of the URL, the account of the token and what was received. */
    public function toCommand(): RecordManualPaymentCommand
    {
        return new RecordManualPaymentCommand(
            (string) $this->route('pedido'),
            (string) $this->user()?->getAuthIdentifier(),
            $this->validated('metodo') === 'efectivo' ? 'cash' : 'sinpe',
            $this->integer('monto'),
            $this->string('referencia')->toString(),
            now()->toIso8601String(),
        );
    }
}
