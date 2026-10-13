<?php

declare(strict_types=1);

it('returns a successful response from the health endpoint', function () {
    $this->get('/up')->assertOk();
});
