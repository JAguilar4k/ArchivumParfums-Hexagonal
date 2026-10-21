
<?php

declare(strict_types=1);

namespace Src\Scheduling\BusinessHours\Domain\Contracts;

use Src\Scheduling\BusinessHours\Domain\BusinessHours;

interface BusinessHoursRepository
{
    public function save(BusinessHours $businessHours): void;
}