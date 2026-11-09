<?php

declare(strict_types=1);

return [
    'ai_failed' => 'El proveedor de IA respondió con un error o no se pudo contactar.',
    'ai_timed_out' => 'El proveedor de IA tardó más de lo permitido.',
    'ai_invalid_output' => 'La respuesta del proveedor de IA no tiene la forma del esquema.',

    // The reasons of the rules recommender (#120): one sentence for each thing the perfume shares with the text.
    'rules' => [
        'notes' => 'Tiene las notas pedidas: :notes.',
        'and' => 'y',
        'time_of_day' => [
            'day' => 'Va bien de día.',
            'afternoon' => 'Va bien por la tarde.',
            'night' => 'Va bien de noche.',
        ],
        'climate' => [
            'warm' => 'Rinde bien en clima cálido.',
            'temperate' => 'Rinde bien en clima templado.',
            'cold' => 'Rinde bien en clima frío.',
        ],
        'occasion' => 'Encaja con la ocasión: :occasion.',
        'budget' => 'Cabe en el presupuesto.',
    ],
];
