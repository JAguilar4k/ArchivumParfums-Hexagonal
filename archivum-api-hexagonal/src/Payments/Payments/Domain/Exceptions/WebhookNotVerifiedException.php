<?php

declare(strict_types=1);

namespace Src\Payments\Payments\Domain\Exceptions;

use Src\Shared\Domain\Exceptions\AuthenticationFailedException;

/** A notice whose origin could not be verified; the answer never says which check failed. */
final class WebhookNotVerifiedException extends AuthenticationFailedException {}
