<?php

declare(strict_types=1);

return [
    'invalid_payable' => 'El recurso no admite pagos.',
    'invalid_payment' => 'El pago debe tener un monto positivo y una pasarela válida.',
    'invalid_transition' => 'El pago no puede cambiar a ese estado.',
    'invalid_refund' => 'El reembolso supera el monto disponible del pago.',
    'unavailable' => 'No pudimos consultar la pasarela. El pago sigue pendiente; no vuelvas a crear el cobro.',
    'unsupported_method' => 'La pasarela configurada no admite ese medio de pago.',
    'invalid_configuration' => 'La pasarela de pruebas no está configurada correctamente.',
    'invalid_exchange_rate' => 'Configura un tipo de cambio positivo antes de cobrar en dólares.',
    'invalid_amount' => 'El monto que devolvió la pasarela no coincide con el pago.',
    'not_found' => 'El pago no existe.',
    'not_payable' => 'El pedido no tiene un monto que se pueda pagar en su estado actual.',
    'refund_not_supported' => 'Esta integración todavía no permite reembolsar con esa pasarela.',
    'unknown_outcome' => 'El resultado del cobro aún no se conoce. Conserva la referencia y consulta su estado.',
    'refund_pending' => 'Espera a que termine el reembolso antes de pagar el saldo.',
];
