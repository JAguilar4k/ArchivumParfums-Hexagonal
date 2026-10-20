<?php

declare(strict_types=1);

return [

    'account_already_on_staff' => 'Esa cuenta ya pertenece al personal de una tienda.',
    'appointment_place_invalid' => 'El lugar de la cita no puede estar vacío ni superar los :max caracteres.',
    'business_not_found' => 'La tienda no existe.',
    'business_registered' => 'Tienda registrada: :name (:slug, :id). Dueño: :email.',
    'create_business_description' => 'Da de alta una tienda con los ajustes por defecto y la cuenta de su dueño.',
    'decant_sizes_invalid' => 'Los tamaños de decant deben ser una lista de números mayores que cero.',
    'demo_password_missing' => 'Hay que definir ARCHIVUM_DEMO_PASSWORD en .env antes de sembrar las tiendas de demostración.',
    'deposit_percent_out_of_range' => 'El porcentaje de depósito debe estar entre :min y :max.',
    'exchange_rate_invalid' => 'El tipo de cambio debe ser mayor que cero.',
    'gateway_invalid' => 'La pasarela por defecto debe ser onvo o paypal.',
    'horizon_before_notice' => 'El horizonte de reserva no puede ser menor que la anticipación mínima.',
    'margin_negative' => 'El margen no puede ser negativo.',
    'name_invalid' => 'El nombre de la tienda es obligatorio y no puede superar los :max caracteres.',
    'name_prompt' => 'Nombre de la tienda',
    'owner_email_prompt' => 'Correo del dueño',
    'owner_name_prompt' => 'Nombre del dueño',
    'owner_password_prompt' => 'Contraseña del dueño',
    'rounding_invalid' => 'El redondeo de los decants debe ser mayor que cero.',
    'slug_already_in_use' => 'Ya existe una tienda con ese slug.',
    'slug_invalid' => 'El slug solo puede tener minúsculas, dígitos y guiones, y no puede superar los :max caracteres.',
    'slug_prompt' => 'Slug de la tienda (minúsculas, dígitos y guiones)',
    'term_negative' => 'Los plazos no pueden ser negativos.',
    'timezone_invalid' => 'La zona horaria no es un identificador IANA válido.',
    'trial_price_negative' => 'El precio de la cita de prueba no puede ser negativo.',

];
