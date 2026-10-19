<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Src\Accounts\Users\Infrastructure\Persistence\Models\CustomerModel;
use Src\Businesses\Members\Infrastructure\Persistence\Models\MemberModel;
use Src\Shared\Infrastructure\Tenancy\BusinessTransaction;
use Tests\Support\RefreshDatabaseAsOwner;

pest()->use(RefreshDatabaseAsOwner::class);

it('pins the business of each factory row before inserting it', function (Closure $create, string $table) {
    $statements = [];
    DB::listen(function (QueryExecuted $query) use (&$statements): void {
        $statements[] = ['sql' => $query->sql, 'bindings' => $query->bindings];
    });

    /** @var Model $row */
    $row = $create();

    $businessId = (string) $row->getAttribute('business_id');
    $scope = array_find_key($statements, fn (array $statement): bool => $statement['bindings'] === [BusinessTransaction::SETTING, $businessId]);
    $insert = array_find_key($statements, fn (array $statement): bool => str_starts_with($statement['sql'], "insert into \"{$table}\""));

    expect($scope)->toBeInt()
        ->and($insert)->toBeInt()
        ->and($scope < $insert)->toBeTrue();
})->with([
    'member' => [fn (): MemberModel => MemberModel::factory()->create(), 'members'],
    'customer' => [fn (): CustomerModel => CustomerModel::factory()->create(), 'customers'],
]);

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
