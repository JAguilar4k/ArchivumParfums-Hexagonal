<?php

declare(strict_types=1);

return [
    'name_empty' => 'El nombre de la tienda no puede estar vacío.',
    'name_too_long' => 'El nombre de la tienda no puede superar los :max caracteres.',
    'slug_invalid' => 'El slug solo puede tener minúsculas, dígitos y guiones, y no puede superar los :max caracteres.',
    'timezone_invalid' => 'La zona horaria no es un identificador IANA válido.',
    'place_too_long' => 'El lugar de la cita no puede superar los :max caracteres.',
    'margin_negative' => 'El margen no puede ser negativo.',
    'decant_sizes_invalid' => 'Los tamaños de decant deben ser una lista de números mayores que cero.',
    'deposit_percent_invalid' => 'El porcentaje de depósito debe estar entre 0 y 100.',
    'rounding_invalid' => 'El redondeo de los decants debe ser mayor que cero.',
    'trial_price_negative' => 'El precio de la cita de prueba no puede ser negativo.',
    'business_not_found' => 'La tienda no existe.',
    'slug_already_in_use' => 'Ya existe una tienda con ese slug.',
    'already_a_member' => 'Esa cuenta ya pertenece al personal de una tienda.',
    'create_business_description' => 'Da de alta una tienda con los ajustes por defecto y la cuenta de su dueño.',
    'name_prompt' => 'Nombre de la tienda',
    'slug_prompt' => 'Slug de la tienda (minúsculas, dígitos y guiones)',
    'owner_name_prompt' => 'Nombre del dueño',
    'owner_email_prompt' => 'Correo del dueño',
    'owner_password_prompt' => 'Contraseña del dueño',
    'business_registered' => 'Tienda registrada: :name (:slug, :id). Dueño: :email.',
    'appointment_place_invalid' => 'El lugar de la cita no puede estar vacío ni superar los :max caracteres.',
    'deposit_percent_out_of_range' => 'El porcentaje de depósito debe estar entre :min y :max.',
    'name_invalid' => 'El nombre de la tienda es obligatorio y no puede superar los :max caracteres.',
];
