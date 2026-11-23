<?php

declare(strict_types=1);

use Aws\MockHandler;
use Aws\Result;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Src\Catalog\Perfumes\Domain\Entities\Perfume;
use Src\Catalog\Perfumes\Infrastructure\Persistence\Models\PerfumeModel;
use Src\Shared\Domain\Contracts\FileStorage;
use Src\Shared\Infrastructure\Tenancy\BusinessTransaction;
use Tests\Support\Businesses\TwoBusinesses;
use Tests\Support\Catalog\FakeImages;
use Tests\Support\Catalog\StoredPerfumes;
use Tests\Support\RefreshDatabaseAsOwner;

use function Pest\Laravel\getJson;
use function Pest\Laravel\post;

pest()->use(RefreshDatabaseAsOwner::class);

beforeEach(function () {
    config(['filesystems.disks.r2.key' => null]);
});

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

function configureR2PerfumePhotos(): string
{
    $publicOrigin = 'https://fotos.example.test';

    config([
        'filesystems.disks.r2.key' => 'test-r2-key',
        'filesystems.disks.r2.secret' => 'test-r2-secret',
        'filesystems.disks.r2.bucket' => 'test-perfumes',
        'filesystems.disks.r2.endpoint' => 'https://r2.example.test',
        'filesystems.disks.r2.url' => $publicOrigin,
    ]);

    return $publicOrigin;
}

it('stores perfume photos in R2 and keeps their public variants readable when write credentials are unavailable', function () {
    ['first' => $first, 'theirs' => $theirs] = photoScenario();
    $perfume = StoredPerfumes::create($first, name: 'Noctis', variants: [[50, 95000, 95000]], photoPath: 'perfumes/previous.png');
    $perfumeId = $perfume->id->value;
    $showcase = '/api/v1/tiendas/'.TwoBusinesses::FIRST.'/perfumes';
    $publicOrigin = configureR2PerfumePhotos();
    Storage::disk('public')->put('perfumes/previous.png', 'previous photo');
    Storage::fake('r2', ['url' => $publicOrigin]);

    getJson("{$showcase}/{$perfumeId}")->assertOk()
        ->assertJsonPath('data.foto_url', Storage::disk('public')->url('perfumes/previous.png'))
        ->assertJsonMissingPath('data.foto_variantes');

    sendPhoto($theirs->id->value, ['foto' => FakeImages::png()])->assertNotFound();
    expect(Storage::disk('r2')->allFiles())->toBe([]);

    $firstUpload = sendPhoto($perfumeId, ['foto' => FakeImages::png('frasco.png')])->assertOk();
    [$firstKey] = Storage::disk('r2')->allFiles('perfumes');
    $firstUpload->assertJsonPath('data.foto_url', "{$publicOrigin}/{$firstKey}");
    Storage::disk('public')->assertMissing('perfumes/previous.png');
    expect($firstKey)->toMatch('#^perfumes/[0-9a-f-]{36}\.png$#');

    $secondUpload = sendPhoto($perfumeId, ['foto' => FakeImages::png()])->assertOk();
    [$currentKey] = Storage::disk('r2')->allFiles('perfumes');
    $photoUrl = "{$publicOrigin}/{$currentKey}";
    $secondUpload->assertJsonPath('data.foto_url', $photoUrl);
    Storage::disk('r2')->assertMissing($firstKey);
    expect(photoPathOf($first, $perfumeId))->toBe("r2:{$photoUrl}")
        ->and(Storage::disk('public')->allFiles())->toBe([]);

    Auth::forgetGuards();
    config(['filesystems.disks.r2.key' => null, 'filesystems.disks.r2.secret' => null]);
    $variants = [
        ['ancho' => 320, 'url' => "{$publicOrigin}/cdn-cgi/image/width=320,fit=contain,format=auto,onerror=redirect/{$currentKey}"],
        ['ancho' => 640, 'url' => "{$publicOrigin}/cdn-cgi/image/width=640,fit=contain,format=auto,onerror=redirect/{$currentKey}"],
        ['ancho' => 960, 'url' => "{$publicOrigin}/cdn-cgi/image/width=960,fit=contain,format=auto,onerror=redirect/{$currentKey}"],
    ];

    getJson($showcase)->assertOk()
        ->assertJsonPath('data.0.foto_url', $photoUrl)
        ->assertJsonPath('data.0.foto_variantes', $variants);
    getJson("{$showcase}/{$perfumeId}")->assertOk()
        ->assertJsonPath('data.foto_url', $photoUrl)
        ->assertJsonPath('data.foto_variantes', $variants);
});

it('exposes a stored Worker photo and its variants without R2 settings', function () {
    TwoBusinesses::create();
    $businessId = TwoBusinesses::idOf(TwoBusinesses::FIRST);
    $origin = 'https://fotos.cuenta.workers.dev';
    $key = 'perfumes/catalogo/frasco.png';
    $photoUrl = "{$origin}/{$key}";
    $perfume = StoredPerfumes::create($businessId, variants: [[50, 95000, 95000]], photoPath: "r2:{$photoUrl}");
    config(['filesystems.disks.r2.url' => null]);

    $response = getJson('/api/v1/tiendas/'.TwoBusinesses::FIRST."/perfumes/{$perfume->id->value}");

    $response->assertOk()
        ->assertJsonPath('data.foto_url', $photoUrl)
        ->assertJsonPath('data.foto_variantes', [
            ['ancho' => 320, 'url' => "{$origin}/imagenes/320/{$key}"],
            ['ancho' => 640, 'url' => "{$origin}/imagenes/640/{$key}"],
            ['ancho' => 960, 'url' => "{$origin}/imagenes/960/{$key}"],
        ]);
});

it('uses local storage when any required R2 setting is absent', function (string $missingSetting) {
    ['first' => $first, 'perfume' => $perfume] = photoScenario();
    Storage::fake('r2');
    configureR2PerfumePhotos();
    config(["filesystems.disks.r2.{$missingSetting}" => null]);

    $id = $perfume->id->value;
    $response = sendPhoto($id, ['foto' => FakeImages::png()])->assertOk();
    $path = photoPathOf($first, $id);

    $response->assertJsonPath('data.foto_url', Storage::disk('public')->url($path));
    Storage::disk('public')->assertExists($path);
    expect(Storage::disk('r2')->allFiles())->toBe([]);
})->with(['key', 'secret', 'bucket', 'endpoint', 'url']);

it('does not use the R2 disk to store logos', function () {
    Storage::fake('public');
    Storage::fake('r2', ['url' => configureR2PerfumePhotos()]);

    $file = FakeImages::png();
    $localPath = app(FileStorage::class)->store('logos', 'png', $file->getPathname());

    Storage::disk('public')->assertExists($localPath);
    expect(Storage::disk('r2')->allFiles())->toBe([]);
});

it('uses the configured R2 directories and exposes the configured image widths in ascending order', function () {
    $origin = configureR2PerfumePhotos();
    Storage::fake('public');
    Storage::fake('r2', ['url' => $origin]);
    config([
        'files.r2_directories' => ['samples'],
        'files.r2_image_widths' => [720, 240],
    ]);

    $source = FakeImages::png();
    $storage = app(FileStorage::class);
    $localPath = $storage->store('perfumes', 'png', $source->getPathname());
    $remotePath = $storage->store('samples', 'png', $source->getPathname());
    [$key] = Storage::disk('r2')->allFiles('samples');

    Storage::disk('public')->assertExists($localPath);
    expect($storage->url($remotePath))->toBe("{$origin}/{$key}")
        ->and($storage->imageVariants($remotePath))->toBe([
            ['width' => 240, 'url' => "{$origin}/cdn-cgi/image/width=240,fit=contain,format=auto,onerror=redirect/{$key}"],
            ['width' => 720, 'url' => "{$origin}/cdn-cgi/image/width=720,fit=contain,format=auto,onerror=redirect/{$key}"],
        ]);
});

it('sends a signed R2 upload without unsupported ACLs', function () {
    $publicOrigin = configureR2PerfumePhotos();
    $handler = new MockHandler([new Result(['ETag' => 'test-etag'])]);
    config(['filesystems.disks.r2.handler' => $handler]);

    $file = FakeImages::png();
    $storage = app(FileStorage::class);
    $path = $storage->store('perfumes', 'png', $file->getPathname());
    $request = $handler->getLastRequest();

    expect($storage->url($path))->toStartWith("{$publicOrigin}/perfumes/")
        ->and($request?->getMethod())->toBe('PUT')
        ->and($request?->getUri()->getHost())->toBe('r2.example.test')
        ->and($request?->getUri()->getPath())->toStartWith('/test-perfumes/perfumes/')
        ->and($request?->hasHeader('Authorization'))->toBeTrue()
        ->and($request?->hasHeader('X-Amz-Acl'))->toBeFalse();
});
