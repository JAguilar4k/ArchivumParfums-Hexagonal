<?php

declare(strict_types=1);

namespace Src\Payments\Payments\Domain\Exceptions;

use Src\Shared\Domain\Exceptions\NotFoundException;

final class PaymentNotFoundException extends NotFoundException {}
