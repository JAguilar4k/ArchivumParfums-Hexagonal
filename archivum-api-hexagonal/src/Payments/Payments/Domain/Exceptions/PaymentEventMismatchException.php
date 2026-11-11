<?php

declare(strict_types=1);

namespace Src\Payments\Payments\Domain\Exceptions;

use Src\Shared\Domain\Exceptions\DomainException;

/** A verified notice that does not match the payment it names: another charge, another amount or another currency. */
final class PaymentEventMismatchException extends DomainException {}
