<?php

declare(strict_types=1);

use Src\Accounts\Users\Infrastructure\Persistence\Models\CustomerModel;
use Src\Businesses\Members\Infrastructure\Persistence\Models\MemberModel;
use Tests\Support\RefreshDatabaseAsOwner;

pest()->use(RefreshDatabaseAsOwner::class);

it('makes an owner or a staff member who does not attend appointments', function () {
    $owner = MemberModel::factory()->owner()->create();
    $staff = MemberModel::factory()->staff()->create();

    expect($owner->role)->toBe('owner')
        ->and($staff->role)->toBe('staff')
        ->and($owner->attends_appointments)->toBeFalse()
        ->and($staff->attends_appointments)->toBeFalse()
        ->and($owner->business_id)->not->toBe($staff->business_id);
});

it('makes a customer with a fictitious test phone', function () {
    expect(CustomerModel::factory()->create()->phone)->toMatch('/^\+5068888\d{4}$/');
});
