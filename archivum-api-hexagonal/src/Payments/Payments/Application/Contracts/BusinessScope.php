<?php

declare(strict_types=1);

namespace Src\Payments\Payments\Application\Contracts;

use Closure;
use Src\Shared\Domain\ValueObjects\BusinessId;

interface BusinessScope
{
    /**
     * Run an operation as one business: the repositories it uses and the transactions it opens reach only the rows of
     * that business. A notice of the provider arrives with no session that could name it.
     *
     * @template TResult
     *
     * @param  Closure(): TResult  $operation
     * @return TResult
     */
    public function run(BusinessId $business, Closure $operation): mixed;
}
