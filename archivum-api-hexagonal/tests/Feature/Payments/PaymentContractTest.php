<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Src\Sales\Orders\Domain\Entities\Order;
use Src\Sales\Orders\Domain\Entities\OrderLine;
use Src\Sales\Orders\Domain\ValueObjects\OrderAmounts;
use Src\Sales\Orders\Domain\ValueObjects\OrderId;
use Src\Sales\Orders\Domain\ValueObjects\OrderLineKind;
use Src\Sales\Orders\Domain\ValueObjects\OrderLineStatus;
use Src\Sales\Orders\Domain\ValueObjects\OrderStatus;
use Src\Sales\Orders\Infrastructure\Http\Resources\OrderResource;
use Src\Shared\Domain\ValueObjects\BusinessId;

use function Pest\Laravel\get;

/** @return array<string, mixed> */
function paymentApiContract(): array
{
    static $document = null;
    if ($document === null) {
        Artisan::call('scramble:clear');
        $document = get('/docs/api.json')->assertOk()->json();
    }

    return $document;
}

it('documents successful reused created and uncertain payment responses with the same data shape', function (): void {
    $document = paymentApiContract();
    $post = $document['paths']['/cuenta/pedidos/{pedido}/pagos']['post'];
    $get = $document['paths']['/cuenta/pagos/{pago}']['get'];

    expect(array_keys($post['responses']))->toContain(200, 201, 202);
    expect(array_keys($get['responses']))->toContain(200, 202);
    foreach ([$post['responses'][200], $post['responses'][201], $post['responses'][202], $get['responses'][200], $get['responses'][202]] as $response) {
        expect($response['content']['application/json']['schema']['properties']['data'])->toBe(['$ref' => '#/components/schemas/PaymentResource']);
    }
    expect($document['components']['schemas']['PaymentResource']['properties']['monto']['type'])->toBe('integer');
    expect($document['components']['schemas']['PaymentResource']['properties']['consulta_pendiente']['type'])->toBe('boolean');
});

it('describes order amounts and each line without empty schemas', function (): void {
    $document = paymentApiContract();
    $order = $document['components']['schemas']['OrderResource'];
    $line = $document['components']['schemas']['OrderLineResource'];

    expect($order['properties']['por_cobrar_ahora'])->toBe(['type' => 'integer']);
    expect($order['properties']['lineas'])->toBe(['type' => 'array', 'items' => ['$ref' => '#/components/schemas/OrderLineResource']]);
    expect($line['type'])->toBe('object');
    expect($line['properties']['precio_unitario'])->toBe(['type' => 'integer']);
    expect($line['properties']['cantidad'])->toBe(['type' => 'integer']);
    expect($line['properties']['tipo']['enum'])->toBe(['botella', 'decant']);
    expect($line['required'])->toContain('id', 'tipo', 'cantidad', 'precio_unitario', 'estado');
});

it('documents role ownership business conflicts and validation as RFC 9457 problems', function (): void {
    $document = paymentApiContract();
    foreach ([['/cuenta/pedidos/{pedido}/pagos', 'post'], ['/cuenta/pagos/{pago}', 'get']] as [$path, $method]) {
        $responses = $document['paths'][$path][$method]['responses'];
        foreach ([401, 403, 404, 409, 422, 500] as $status) {
            expect($responses[$status]['content'])->toHaveKey('application/problem+json');
        }
    }
});

it('describes the typed bottle and movement DTOs used by the panel', function (): void {
    $schemas = paymentApiContract()['components']['schemas'];

    expect($schemas['PanelBottle']['properties']['ml_disponibles'])->toBe(['type' => 'integer']);
    expect($schemas['PanelMovement']['properties']['ml'])->toBe(['type' => 'integer']);
    expect($schemas['PanelMovement']['properties']['tipo']['enum'])->toContain('reserva', 'liberacion', 'consumo', 'ajuste');
});

it('keeps the order JSON and its unwrapped line list unchanged while clamping the current due', function (OrderStatus $status, int $paid, int $expectedDue): void {
    $order = Order::reconstitute(
        new OrderId('019a0010-0000-7000-8000-000000000001'),
        new BusinessId('019a0010-0000-7000-8000-000000000002'),
        '019a0010-0000-7000-8000-000000000003',
        [OrderLine::reconstitute('019a0010-0000-7000-8000-000000000004', OrderLineKind::Bottle, '019a0010-0000-7000-8000-000000000005', null, null, 1, 15000, OrderLineStatus::Pending, perfumeName: 'Aroma ficticio', variantMl: 100)],
        $status, new OrderAmounts(15000, 7500, $paid, 7500), new DateTimeImmutable('2026-10-25T12:00:00-06:00'), new DateTimeImmutable('2026-10-25T12:30:00-06:00'),
    );

    $data = (new OrderResource($order))->response()->getData(true);

    expect($data['data']['por_cobrar_ahora'])->toBe($expectedDue);
    expect($data['data']['lineas'])->toBe([[
        'id' => '019a0010-0000-7000-8000-000000000004', 'tipo' => 'botella',
        'presentacion_id' => '019a0010-0000-7000-8000-000000000005', 'botella_id' => null,
        'perfume' => 'Aroma ficticio', 'ml' => null, 'presentacion_ml' => 100,
        'cantidad' => 1, 'precio_unitario' => 15000, 'estado' => 'pendiente',
    ]]);
})->with([[OrderStatus::PendingPayment, 0, 7500], [OrderStatus::PendingPayment, 8000, 0], [OrderStatus::Confirmed, 7500, 0]]);
