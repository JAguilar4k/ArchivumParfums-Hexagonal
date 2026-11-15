<?php

declare(strict_types=1);

namespace Src\Recommendations\Recommender\Domain\Exceptions;

use Src\Shared\Domain\Exceptions\DomainException;

/**
 * The AI provider gave no recommendation that can be used: it failed, it took longer than the limit or it answered
 * something that is not the declared schema. Its translation key says which. The recommendation then comes from the
 * rules recommender (R50, #120); this exception never reaches the customer.
 */
final class AiUnavailableException extends DomainException {}
