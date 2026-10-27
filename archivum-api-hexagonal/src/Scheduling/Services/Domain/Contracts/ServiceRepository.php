<?php

declare(strict_types=1);

namespace Src\Scheduling\Services\Domain\Contracts;

use Src\Scheduling\Services\Domain\Entities\Service;

interface ServiceRepository
{
    public function save(Service $service): void;
}
