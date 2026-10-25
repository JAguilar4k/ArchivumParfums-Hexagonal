<?php

declare(strict_types=1);

namespace Src\Sales\Orders\Domain\Exceptions;

use Src\Shared\Domain\Exceptions\NotFoundException;

final class OrderNotFoundException extends NotFoundException {}
