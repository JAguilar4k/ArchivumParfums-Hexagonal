<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Src\Shared\Infrastructure\Tenancy\BusinessTransaction;

const PINNED_BUSINESS = '0192f0c4-0000-7000-9000-000000000001';

/** Read the business pinned in the current transaction, or an empty string. */
function pinnedBusiness(): string
{
    return (string) DB::scalar("select coalesce(current_setting('app.business_id', true), '')");
}

it('pins the business only while its transaction is open', function () {
    $inside = (new BusinessTransaction)->run(PINNED_BUSINESS, fn (): string => pinnedBusiness());

    expect($inside)->toBe(PINNED_BUSINESS)
        ->and(pinnedBusiness())->toBe('');
});

it('pins no business when it receives none', function () {
    $inside = (new BusinessTransaction)->run(null, fn (): string => pinnedBusiness());

    expect($inside)->toBe('');
});

it('changes the business of an open transaction', function () {
    $inside = (new BusinessTransaction)->run(null, function (): string {
        (new BusinessTransaction)->scope(PINNED_BUSINESS);

        return pinnedBusiness();
    });

    expect($inside)->toBe(PINNED_BUSINESS);
});
