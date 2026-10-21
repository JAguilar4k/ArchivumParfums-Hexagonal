<?php

declare(strict_types=1);

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Src\Catalog\Perfumes\Domain\Entities\Perfume;
use Src\Catalog\Perfumes\Infrastructure\Persistence\Models\PerfumeModel;
use Src\Shared\Infrastructure\Tenancy\BusinessTransaction;
use Tests\Support\Businesses\TwoBusinesses;
use Tests\Support\Catalog\FakeImages;
use Tests\Support\Catalog\StoredPerfumes;
use Tests\Support\RefreshDatabaseAsOwner;

use function Pest\Laravel\post;

pest()->use(RefreshDatabaseAsOwner::class);

/** The path of the photo that a perfume has stored, with the database scoped to its business. */
function storedPhotoOf(string $businessId, string $perfumeId): ?string
{
    app(BusinessTransaction::class)->scope($businessId);

    return PerfumeModel::query()->findOrFail($perfumeId)->image_path;
}

/** The path of the photo that a perfume must have stored, with the database scoped to its business. */
function photoPathOf(string $businessId, string $perfumeId): string
{
    return storedPhotoOf($businessId, $perfumeId) ?? throw new LogicException("The perfume {$perfumeId} has no photo.");
}

/**
 * Send a file as the photo of a perfume, as a form, asking for JSON like every client of the API.
 *
 * @param  array<string, mixed>  $body
 * @return TestResponse<JsonResponse>
 */
function sendPhoto(string $perfumeId, array $body): TestResponse
{
    return post("/api/v1/panel/perfumes/{$perfumeId}/foto", $body, ['Accept' => 'application/json']);
}

/**
 * Two businesses with a perfume each and the owner of the first signed in, with the files in a fake disk.
 *
 * @return array{first: string, second: string, perfume: Perfume, theirs: Perfume}
 */
function photoScenario(): array
{
    Storage::fake('public');
    TwoBusinesses::create();
    $first = TwoBusinesses::idOf(TwoBusinesses::FIRST);
    $second = TwoBusinesses::idOf(TwoBusinesses::SECOND);
    $perfume = StoredPerfumes::create($first);
    $theirs = StoredPerfumes::create($second, name: 'Gypsy Water');
    TwoBusinesses::actAsOwnerOf(TwoBusinesses::FIRST);

    return ['first' => $first, 'second' => $second, 'perfume' => $perfume, 'theirs' => $theirs];
}

it('keeps the photo of the perfume under a generated name and answers its address', function () {
    ['first' => $first, 'second' => $second, 'perfume' => $perfume, 'theirs' => $theirs] = photoScenario();
    $response = sendPhoto($perfume->id->value, ['foto' => FakeImages::png('frasco.png')])->assertOk();

    $path = photoPathOf($first, $perfume->id->value);
    expect($path)->toMatch('#^perfumes/[0-9a-f-]{36}\.png$#')
        ->and($path)->not->toContain('frasco')
        ->and($response->json('data'))->toBe(['foto_url' => Storage::disk('public')->url($path)]);

    Storage::disk('public')->assertExists($path);
});

it('deletes the previous photo when it is replaced', function () {
    ['first' => $first, 'second' => $second, 'perfume' => $perfume, 'theirs' => $theirs] = photoScenario();
    sendPhoto($perfume->id->value, ['foto' => FakeImages::png()])->assertOk();
    $firstPath = photoPathOf($first, $perfume->id->value);

    sendPhoto($perfume->id->value, ['foto' => FakeImages::png()])->assertOk();
    $secondPath = photoPathOf($first, $perfume->id->value);

    expect($secondPath)->not->toBe($firstPath);
    Storage::disk('public')->assertMissing($firstPath);
    Storage::disk('public')->assertExists($secondPath);
});

it('refuses the files that are not an image of an accepted type, whatever their name says, and keeps the photo it had', function (Closure $file) {
    ['first' => $first, 'second' => $second, 'perfume' => $perfume, 'theirs' => $theirs] = photoScenario();
    sendPhoto($perfume->id->value, ['foto' => FakeImages::png()])->assertOk();
    $current = photoPathOf($first, $perfume->id->value);

    $response = sendPhoto($perfume->id->value, ['foto' => $file()])
        ->assertUnprocessable()
        ->assertHeader('Content-Type', 'application/problem+json');

    expect($response->json('errores.foto'))->toBe(['La foto debe ser una imagen PNG, JPG o WEBP de hasta 2 MB.'])
        ->and(storedPhotoOf($first, $perfume->id->value))->toBe($current)
        ->and(Storage::disk('public')->allFiles('perfumes'))->toHaveCount(1);
})->with([
    'a pdf renamed png' => [fn () => FakeImages::pdfNamedPng()],
    'a gif renamed png' => [fn () => FakeImages::gif()],
    'a file of more than 2 MB' => [fn () => FakeImages::pngOfKilobytes(3000)],
]);

it('answers 404 for a perfume of the other business exactly like for one that does not exist and stores nothing', function (string $id) {
    ['first' => $first, 'second' => $second, 'perfume' => $perfume, 'theirs' => $theirs] = photoScenario();
    sendPhoto($id, ['foto' => FakeImages::png()])
        ->assertNotFound()
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath('type', rtrim((string) config('app.url'), '/').'/problemas/no-encontrado');

    expect(Storage::disk('public')->allFiles('perfumes'))->toBe([]);
})->with([
    'unknown' => '0192f0c4-0000-7000-9000-999999999999',
    'not an identity' => 'no-es-un-uuid',
]);

it('does not touch the photo of the perfume of the other business', function () {
    ['first' => $first, 'second' => $second, 'perfume' => $perfume, 'theirs' => $theirs] = photoScenario();
    sendPhoto($theirs->id->value, ['foto' => FakeImages::png()])->assertNotFound();

    expect(storedPhotoOf($second, $theirs->id->value))->toBeNull()
        ->and(Storage::disk('public')->allFiles('perfumes'))->toBe([]);
});

it('answers 403 to the staff and the customers and stores nothing', function (string $email) {
    ['first' => $first, 'second' => $second, 'perfume' => $perfume, 'theirs' => $theirs] = photoScenario();
    TwoBusinesses::actAs($email);

    sendPhoto($perfume->id->value, ['foto' => FakeImages::png()])->assertForbidden()->assertHeader('Content-Type', 'application/problem+json');

    expect(storedPhotoOf($first, $perfume->id->value))->toBeNull()
        ->and(Storage::disk('public')->allFiles('perfumes'))->toBe([]);
})->with([
    'advisor' => TwoBusinesses::ADVISOR,
    'customer' => TwoBusinesses::CUSTOMER,
]);

it('answers 401 when the request has no token', function () {
    ['first' => $first, 'second' => $second, 'perfume' => $perfume, 'theirs' => $theirs] = photoScenario();
    Auth::forgetGuards();

    sendPhoto($perfume->id->value, ['foto' => FakeImages::png()])->assertUnauthorized()->assertHeader('Content-Type', 'application/problem+json');
});
