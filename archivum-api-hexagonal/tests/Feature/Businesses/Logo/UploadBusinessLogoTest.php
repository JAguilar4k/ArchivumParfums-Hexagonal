<?php

declare(strict_types=1);

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\Support\Businesses\TwoBusinesses;
use Tests\Support\Catalog\FakeImages;
use Tests\Support\RefreshDatabaseAsOwner;

use function Pest\Laravel\post;

pest()->use(RefreshDatabaseAsOwner::class);

const LOGO_ENDPOINT = '/api/v1/panel/tienda/logo';

/** The path of the logo that a business has stored, by its slug. */
function storedLogoOf(string $slug): ?string
{
    return DB::table('businesses')->where('slug', $slug)->value('logo_path');
}

/** The path of the logo that a business must have stored, by its slug. */
function logoPathOf(string $slug): string
{
    return storedLogoOf($slug) ?? throw new LogicException("The business {$slug} has no logo.");
}

/**
 * Send a file as the logo, as a form, asking for JSON like every client of the API.
 *
 * @param  array<string, mixed>  $body
 * @return TestResponse<JsonResponse>
 */
function sendLogo(array $body): TestResponse
{
    return post(LOGO_ENDPOINT, $body, ['Accept' => 'application/json']);
}

beforeEach(function () {
    Storage::fake('public');
    TwoBusinesses::create();
    DB::table('businesses')->where('slug', TwoBusinesses::SECOND)->update(['logo_path' => 'logos/de-la-otra-tienda.png']);
    Storage::disk('public')->put('logos/de-la-otra-tienda.png', 'contenido ajeno');
    TwoBusinesses::actAsOwnerOf(TwoBusinesses::FIRST);
});

it('keeps the logo of the business of the session under a generated name and answers its address', function () {
    $response = sendLogo(['logo' => FakeImages::png('mi-logo.png')])->assertOk();

    $path = logoPathOf(TwoBusinesses::FIRST);
    expect($path)->toMatch('#^logos/[0-9a-f-]{36}\.png$#')
        ->and($path)->not->toContain('mi-logo')
        ->and($response->json('data'))->toBe(['logo_url' => Storage::disk('public')->url($path)]);

    Storage::disk('public')->assertExists($path);
});

it('deletes the previous logo when it is replaced', function () {
    sendLogo(['logo' => FakeImages::png()])->assertOk();
    $firstPath = logoPathOf(TwoBusinesses::FIRST);

    sendLogo(['logo' => FakeImages::png()])->assertOk();
    $secondPath = logoPathOf(TwoBusinesses::FIRST);

    expect($secondPath)->not->toBe($firstPath);
    Storage::disk('public')->assertMissing($firstPath);
    Storage::disk('public')->assertExists($secondPath);
});

it('refuses the files that are not an image of an accepted type, whatever their name says, and keeps the logo it had', function (Closure $file) {
    sendLogo(['logo' => FakeImages::png()])->assertOk();
    $current = logoPathOf(TwoBusinesses::FIRST);

    $response = sendLogo(['logo' => $file()])
        ->assertUnprocessable()
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath('type', rtrim((string) config('app.url'), '/').'/problemas/datos-invalidos');

    expect($response->json('errores.logo'))->toBe(['El logo debe ser una imagen PNG, JPG o WEBP de hasta 2 MB.'])
        ->and(storedLogoOf(TwoBusinesses::FIRST))->toBe($current)
        ->and(Storage::disk('public')->allFiles('logos'))->toHaveCount(2);
})->with([
    'a pdf renamed png' => [fn () => FakeImages::pdfNamedPng()],
    'a gif renamed png' => [fn () => FakeImages::gif()],
    'a file of more than 2 MB' => [fn () => FakeImages::pngOfKilobytes(3000)],
]);

it('answers 422 on the field logo when no file is sent', function () {
    $errors = sendLogo([])->assertUnprocessable()->json('errores');

    expect(array_keys($errors))->toBe(['logo']);
});

it('leaves the logo of the other business alone', function () {
    sendLogo(['logo' => FakeImages::png()])->assertOk();

    expect(storedLogoOf(TwoBusinesses::SECOND))->toBe('logos/de-la-otra-tienda.png');
    Storage::disk('public')->assertExists('logos/de-la-otra-tienda.png');

    TwoBusinesses::actAsOwnerOf(TwoBusinesses::SECOND);
    sendLogo(['logo' => FakeImages::png()])->assertOk();

    expect(storedLogoOf(TwoBusinesses::SECOND))->not->toBe('logos/de-la-otra-tienda.png')
        ->and(storedLogoOf(TwoBusinesses::FIRST))->toMatch('#^logos/[0-9a-f-]{36}\.png$#');
    Storage::disk('public')->assertMissing('logos/de-la-otra-tienda.png');
});

it('answers 403 to the staff and the customers and stores nothing', function (string $email) {
    TwoBusinesses::actAs($email);

    sendLogo(['logo' => FakeImages::png()])->assertForbidden()->assertHeader('Content-Type', 'application/problem+json');

    expect(storedLogoOf(TwoBusinesses::FIRST))->toBeNull()
        ->and(Storage::disk('public')->allFiles('logos'))->toHaveCount(1);
})->with([
    'advisor' => TwoBusinesses::ADVISOR,
    'customer' => TwoBusinesses::CUSTOMER,
]);

it('answers 401 when the request has no token', function () {
    Auth::forgetGuards();

    sendLogo(['logo' => FakeImages::png()])->assertUnauthorized()->assertHeader('Content-Type', 'application/problem+json');
});
