<?php

declare(strict_types=1);

return [

    'stateful' => [],

    'guard' => [],

    // Minutes a token lasts; POST /sesiones/actual/renovacion swaps a valid one for a new one (#103).
    'expiration' => (int) env('SANCTUM_EXPIRATION', 60),

    'routes' => false,

];
