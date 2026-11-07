<?php

declare(strict_types=1);

return [

    // Only archivum-web may call the API from a browser (#109). As a pattern, because with a single allowed origin the
    // CORS service sends that origin to everybody; this way, another origin gets no Access-Control-Allow-Origin at all.
    'allowed_origins' => [],
    'allowed_origins_patterns' => ['#^'.preg_quote(config()->string('archivum.web_origin'), '#').'$#'],

    // The web client shows the request identifier when it reports an error.
    'exposed_headers' => ['X-Request-Id'],

];
