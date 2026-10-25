<?php

declare(strict_types=1);

return [
    'not_found' => 'Ese pedido o esa línea no existe.',
    'empty' => 'El pedido debe llevar al menos una línea.',
    'invalid_line' => 'La línea del pedido no es válida.',
    'invalid_deposit' => 'El porcentaje del depósito no es válido.',
    'invalid_business' => 'El pedido no pertenece a la tienda de la operación.',
    'transition' => 'El pedido no permite ese cambio de estado.',
    'line_transition' => 'Esa línea no permite ese cambio de estado.',
    'cannot_cancel' => 'Este pedido ya no se puede cancelar.',
    'cannot_prepare' => 'El pedido aún no tiene un pago confirmado.',
    'no_decants' => 'El pedido no tiene decants pendientes de preparar.',
    'not_ready' => 'Falta recibir botellas o preparar decants.',
    'balance_due' => 'El pedido aún tiene saldo pendiente.',
    'refund_pending' => 'El reembolso pendiente debe resolverse antes de entregar el pedido.',
    'payment_not_allowed' => 'El pedido no admite ese pago.',
    'product_unavailable' => 'Ese producto no está disponible para pedirlo.',
    'decant_size' => 'Ese tamaño de decant no está disponible en esta tienda.',
    'line_kind' => 'La línea debe incluir una presentación o una botella para decants.',
    'lineas' => 'líneas del pedido',
];
