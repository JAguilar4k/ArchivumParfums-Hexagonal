<?php

declare(strict_types=1);

return [
    'invalid_payable' => 'El recurso no admite pagos.',
    'invalid_payment' => 'El pago debe tener un monto positivo y una pasarela válida.',
    'invalid_transition' => 'El pago no puede cambiar a ese estado.',
    'invalid_refund' => 'El reembolso supera el monto disponible del pago.',
    'not_refundable' => 'El pago no tiene dinero confirmado que se pueda devolver.',
    'unavailable' => 'No pudimos consultar la pasarela. El pago sigue pendiente; no vuelvas a crear el cobro.',
    'unsupported_method' => 'La pasarela configurada no admite ese medio de pago.',
    'invalid_configuration' => 'La pasarela de pruebas no está configurada correctamente.',
    'invalid_exchange_rate' => 'Configura un tipo de cambio positivo antes de cobrar en dólares.',
    'invalid_amount' => 'El monto que devolvió la pasarela no coincide con el pago.',
    'not_found' => 'El pago no existe.',
    'not_payable' => 'El pedido no tiene un monto que se pueda pagar en su estado actual.',
    'unknown_outcome' => 'El resultado del cobro aún no se conoce. Conserva la referencia y consulta su estado.',
    'refund_pending' => 'Espera a que termine el reembolso antes de pagar el saldo.',
    'webhook_not_verified' => 'No se pudo verificar el origen del aviso.',
    'event_not_processed' => 'No se pudo procesar el aviso de la pasarela.',
    'invalid_webhook' => 'El aviso de la pasarela no tiene el formato esperado.',
    'event_reference_mismatch' => 'El aviso de la pasarela no corresponde a este pago.',
    'event_amount_mismatch' => 'El monto o la moneda del aviso no coinciden con el pago.',
    'reconcile_payments_description' => 'Consulta a la pasarela los pagos pendientes de todas las tiendas que ya esperaron su aviso, los pagos que el vencimiento de su pedido o de su paquete cerró en las últimas 24 horas y los reembolsos que ya aceptó, y aplica su estado real.',
    'payments_reconciled' => '{0} Ningún pago pendiente cambió de estado.|{1} Cambió de estado 1 pago pendiente.|[2,*] Cambiaron de estado :count pagos pendientes.',
    'payments_not_reconciled' => '{1} No se pudo revisar 1 pago; sigue pendiente para la siguiente corrida.|[2,*] No se pudieron revisar :count pagos; siguen pendientes para la siguiente corrida.',
    'refunds_reconciled' => '{0} Ningún reembolso pendiente cambió de estado.|{1} Cambió de estado 1 reembolso pendiente.|[2,*] Cambiaron de estado :count reembolsos pendientes.',
    'refunds_not_reconciled' => '{1} No se pudo revisar 1 reembolso; sigue pendiente para la siguiente corrida.|[2,*] No se pudieron revisar :count reembolsos; siguen pendientes para la siguiente corrida.',
];
