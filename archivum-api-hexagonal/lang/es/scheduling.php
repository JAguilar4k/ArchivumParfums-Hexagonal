<?php

declare(strict_types=1);

return [
    'service.name_required' => 'El nombre del servicio es obligatorio.',
    'appointment.invalid_transition' => 'La cita no puede pasar de :from a :to.',
    'appointment.change_not_allowed' => 'La cita solo se cambia o se cancela hasta :hours horas antes.',
    'appointment.too_many_perfumes' => 'La cita no puede probar más de :max perfumes.',
    'appointment.repeated_perfume' => 'Un perfume no se puede elegir dos veces en la misma cita.',
    'appointment.invalid_order' => 'El fin de la cita debe ser posterior a su inicio.',
    'appointment.invalid_block' => 'El margen de la cita no puede terminar antes que la cita.',
    'service.invalid_duration' => 'La duración del servicio debe ser mayor a cero.',
    'service.invalid_buffer' => 'El margen entre citas no puede ser negativo.',
    'service.invalid_max_perfumes' => 'El máximo de perfumes no puede ser negativo.',
    'invalid_time_format' => 'La hora debe tener el formato HH:MM.',
    'time_range_invalid_order' => 'La hora de fin debe ser posterior a la hora de inicio.',
    'time_slot_overlaps' => 'El tramo de horario se traslapa con otro existente.',
    'block.reason_required' => 'El motivo del bloqueo es obligatorio.',
    'schedule_block.invalid_order' => 'Error: orden inválida. La hora de fin debe ser posterior a la hora de inicio.',
];
