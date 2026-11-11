<?php

declare(strict_types=1);

return [
    'name_invalid' => 'El nombre del paquete es obligatorio y admite hasta :max caracteres.',
    'decants_invalid' => 'La cantidad de decants debe ser un número entero entre 1 y :max.',
    'ml_invalid' => 'Los ml por decant deben ser un número entero entre 1 y :max.',
    'size_not_sold' => 'Los ml por decant deben ser uno de los tamaños de decant de la tienda: :sizes ml.',
    'price_invalid' => 'El precio del paquete debe ser un número entero de colones entre 1 y :max.',
    'validity_invalid' => 'La vigencia debe ser un número entero de días entre 1 y :max.',
    'cap_invalid' => 'El precio máximo por ml debe ser un número entero de colones entre 1 y :max.',
    'invalid_pack' => 'Los decants del paquete no son válidos.',
    'plan_not_found' => 'Ese paquete no existe en esta tienda.',
    'transition' => 'El paquete no puede pasar a ese estado.',
    'not_active' => 'El paquete todavía espera la confirmación de su pago.',
    'expired' => 'El paquete venció y ya no se puede usar.',
    'exhausted' => 'El paquete ya no tiene decants disponibles.',
    'ml_mismatch' => 'Este paquete solo cubre decants de :ml ml.',
    'over_price_cap' => 'Ese perfume supera el precio por ml que cubre el paquete.',
];
