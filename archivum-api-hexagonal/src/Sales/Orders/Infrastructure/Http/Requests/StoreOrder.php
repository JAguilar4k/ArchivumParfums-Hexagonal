<?php

declare(strict_types=1);

namespace Src\Sales\Orders\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use LogicException;
use Src\Sales\Orders\Application\DTOs\PlaceOrderCommand;
use Src\Shared\Domain\ValueObjects\Colones;

final class StoreOrder extends FormRequest
{
    /**
     * Only product identities and quantities come from the browser.
     *
     * @return array<string,list<string>>
     */
    public function rules(): array
    {
        return [
            'lineas' => ['required', 'array', 'min:1'],
            'lineas.*' => ['required', 'array'],
            'lineas.*.presentacion_id' => ['required_without:lineas.*.botella_id', 'prohibits:lineas.*.botella_id', 'uuid'],
            'lineas.*.botella_id' => ['required_without:lineas.*.presentacion_id', 'prohibits:lineas.*.presentacion_id', 'uuid'],
            'lineas.*.cantidad' => ['required_with:lineas.*.presentacion_id', 'integer', 'min:1', 'max:'.Colones::MAX],
            'lineas.*.ml' => ['required_with:lineas.*.botella_id', 'integer', 'min:1', 'max:'.Colones::MAX],
        ];
    }

    /** @return array<string,string> */
    public function attributes(): array
    {
        return ['lineas' => __('orders.lineas'), 'lineas.*.presentacion_id' => 'presentación', 'lineas.*.botella_id' => 'botella', 'lineas.*.ml' => 'mililitros', 'lineas.*.cantidad' => 'cantidad'];
    }

    public function toCommand(): PlaceOrderCommand
    {
        /** @var list<array{presentacion_id?:string,botella_id?:string,cantidad?:int,ml?:int}> $input */
        $input = $this->validated('lineas');
        $lines = [];

        foreach ($input as $line) {
            if (isset($line['presentacion_id'], $line['cantidad'])) {
                $lines[] = ['kind' => 'bottle', 'reference' => $line['presentacion_id'], 'ml' => null, 'quantity' => (int) $line['cantidad']];
            } elseif (isset($line['botella_id'], $line['ml'])) {
                $lines[] = ['kind' => 'decant', 'reference' => $line['botella_id'], 'ml' => (int) $line['ml'], 'quantity' => 1];
            } else {
                throw new LogicException('A validated order line lacks its product or quantity.');
            }
        }

        return new PlaceOrderCommand((string) $this->user()?->getAuthIdentifier(), $lines, now()->toIso8601String());
    }
}
