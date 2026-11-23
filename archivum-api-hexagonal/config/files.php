<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Disk of the uploaded files
    |--------------------------------------------------------------------------
    |
    | The public disk serves local store logos and perfume photos from /storage.
    | When R2 is configured, new perfume photos are stored there. Existing photos
    | keep the location recorded at upload time.
    |
    */

    'disk' => 'public',
    'r2_directories' => ['perfumes'],
    'r2_image_widths' => [320, 640, 960],

];
