<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Src\Accounts\Users\Infrastructure\Persistence\Models\CustomerModel;
use Src\Businesses\Members\Infrastructure\Persistence\Models\MemberModel;
use Src\Shared\Infrastructure\Tenancy\BusinessTransaction;
use Tests\Support\Businesses\StaffedBusiness;
use Tests\Support\RefreshDatabaseAsOwner;

use function Pest\Laravel\getJson;

pest()->use(RefreshDatabaseAsOwner::class);

it('signs in the owner, the staff member and the customer of the same business', function () {
    $business = actingAsOwner();
    getJson('/api/v1/cuenta')
        ->assertOk()
        ->assertJsonPath('data.id', $business->owner->id)
        ->assertJsonPath('data.rol', 'dueno')
        ->assertJsonPath('data.tienda.id', $business->business->id);

    actingAsStaff($business);
    getJson('/api/v1/cuenta')
        ->assertOk()
        ->assertJsonPath('data.id', $business->staff->id)
        ->assertJsonPath('data.rol', 'colaborador')
        ->assertJsonPath('data.tienda.id', $business->business->id);

    actingAsCustomer($business);
    getJson('/api/v1/cuenta')
        ->assertOk()
        ->assertJsonPath('data.id', $business->customer->id)
        ->assertJsonPath('data.rol', 'cliente')
        ->assertJsonPath('data.tienda', null);
});

it('answers each role of the permission matrix as expected', function (string $role) {
    actingAsRole($role);

    getJson('/api/v1/cuenta')->assertStatus($role === 'visitante' ? 401 : 200);
})->with('roles');

it('signs in the staff of another business outside the business of the test', function (string $role) {
    $own = StaffedBusiness::create();
    $other = actingAsRole($role);

    expect($other->business->id)->not->toBe($own->business->id);
    getJson('/api/v1/cuenta')
        ->assertOk()
        ->assertJsonPath('data.rol', $role)
        ->assertJsonPath('data.tienda.id', $other->business->id);
})->with('staff of another business');

it('creates the fixture in its own business and restores the previous transaction scope', function () {
    $previous = '0192f0c4-7b1e-7c3a-9f1d-2b6a4e8c0d99';
    $transaction = new BusinessTransaction;
    $transaction->scope($previous);

    $fixture = StaffedBusiness::create();

    expect(DB::scalar('select current_setting(?, true)', [BusinessTransaction::SETTING]))->toBe($previous);

    $transaction->run($fixture->business->id, function () use ($fixture): void {
        expect(MemberModel::query()->where('business_id', $fixture->business->id)->count())->toBe(2)
            ->and(CustomerModel::query()->where('business_id', $fixture->business->id)->count())->toBe(1);
    });
});
