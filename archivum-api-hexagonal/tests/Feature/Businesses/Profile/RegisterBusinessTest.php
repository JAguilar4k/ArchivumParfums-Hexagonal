<?php

declare(strict_types=1);

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Src\Accounts\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Businesses\Members\Infrastructure\Persistence\Models\MemberModel;
use Src\Businesses\Profile\Application\DTOs\RegisterBusinessCommand;
use Src\Businesses\Profile\Application\UseCases\RegisterBusiness;
use Src\Businesses\Profile\Domain\ValueObjects\BusinessTimezone;
use Src\Businesses\Profile\Infrastructure\Persistence\Models\BusinessModel;
use Src\Shared\Domain\Exceptions\InvalidValueException;
use Tests\Support\RefreshDatabaseAsOwner;

use function Pest\Laravel\assertDatabaseCount;

pest()->use(RefreshDatabaseAsOwner::class);

/** Build the command of a business, overriding only what a scenario changes. */
function businessRegistration(
    string $name = 'ArchivumParfums',
    string $slug = 'archivumparfums',
    string $ownerPassword = 'clave-segura',
): RegisterBusinessCommand {
    return new RegisterBusinessCommand(
        $name,
        $slug,
        'Valeria Solano Ruiz',
        'duena@archivumparfums.test',
        $ownerPassword,
        BusinessTimezone::DEFAULT,
        'Escazú, San Rafael',
        true,
    );
}

/**
 * Record the SQL of every statement the test runs from now on.
 *
 * @return ArrayObject<int, string>
 */
function recordStatements(): ArrayObject
{
    /** @var ArrayObject<int, string> $statements */
    $statements = new ArrayObject;
    DB::listen(function (QueryExecuted $query) use ($statements): void {
        $statements[] = $query->sql;
    });

    return $statements;
}

/**
 * Assert the business was inserted during the attempt, so the rollback proves something, and is gone after it.
 *
 * @param  ArrayObject<int, string>  $statements
 */
function expectBusinessInsertRolledBack(ArrayObject $statements, int $businessesKept): void
{
    expect(array_any($statements->getArrayCopy(), fn (string $sql): bool => str_starts_with($sql, 'insert into "businesses"')))
        ->toBeTrue('The business was never inserted, so the rollback proves nothing.');
    assertDatabaseCount('businesses', $businessesKept);
}

it('stores the business and its owner together, with the owner on its staff', function () {
    $business = app(RegisterBusiness::class)->execute(businessRegistration());

    $model = BusinessModel::query()->findOrFail($business->id->value);
    $owner = UserModel::query()->where('email', 'duena@archivumparfums.test')->sole();
    $member = MemberModel::query()->sole();

    expect($model->slug)->toBe('archivumparfums')
        ->and($model->location_text)->toBe('Escazú, San Rafael')
        ->and($owner->name)->toBe('Valeria Solano Ruiz')
        ->and($owner->password)->not->toBe('clave-segura')
        ->and(Hash::check('clave-segura', $owner->password))->toBeTrue()
        ->and($member->business_id)->toBe($business->id->value)
        ->and($member->user_id)->toBe($owner->id)
        ->and($member->role)->toBe('owner')
        ->and($member->attends_appointments)->toBeTrue();
});

it('leaves no second business behind when the owner email is repeated', function () {
    $registerBusiness = app(RegisterBusiness::class);
    $registerBusiness->execute(businessRegistration());
    $statements = recordStatements();

    expect(fn () => $registerBusiness->execute(businessRegistration('Esencias del Norte', 'esencias-del-norte')))
        ->toThrow(InvalidValueException::class, 'accounts.email_already_registered');

    expectBusinessInsertRolledBack($statements, businessesKept: 1);
    assertDatabaseCount('users', 1);
    assertDatabaseCount('members', 1);
});

it('leaves neither a business nor an account behind when the owner password is short', function () {
    $statements = recordStatements();

    expect(fn () => app(RegisterBusiness::class)->execute(businessRegistration(ownerPassword: '1234567')))
        ->toThrow(InvalidValueException::class, 'accounts.password_too_short');

    expectBusinessInsertRolledBack($statements, businessesKept: 0);
    assertDatabaseCount('users', 0);
});
