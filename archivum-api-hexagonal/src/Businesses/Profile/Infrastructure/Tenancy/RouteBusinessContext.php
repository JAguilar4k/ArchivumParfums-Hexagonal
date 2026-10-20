<?php

declare(strict_types=1);

namespace Src\Businesses\Profile\Infrastructure\Tenancy;

use Illuminate\Http\Request;
use Src\Businesses\Profile\Application\UseCases\FindBusinessBySlug;
use Src\Businesses\Profile\Domain\Exceptions\BusinessNotFoundException;
use Src\Shared\Domain\Contracts\BusinessContext;
use Src\Shared\Domain\ValueObjects\BusinessId;

final class RouteBusinessContext implements BusinessContext
{
    public const string PARAMETER = 'tienda';

    private ?BusinessId $business = null;

    /** Receive the request and the use case that finds a business by its slug. */
    public function __construct(
        private readonly Request $request,
        private readonly FindBusinessBySlug $findBusiness,
    ) {}

    /**
     * Answer the business of the {tienda} slug of the URL; an unknown slug is not found.
     *
     * @throws BusinessNotFoundException
     */
    public function current(): BusinessId
    {
        if ($this->business !== null) {
            return $this->business;
        }

        $slug = $this->request->route(self::PARAMETER);

        return $this->business = $this->findBusiness->execute(is_string($slug) ? $slug : '')->id;
    }
}
