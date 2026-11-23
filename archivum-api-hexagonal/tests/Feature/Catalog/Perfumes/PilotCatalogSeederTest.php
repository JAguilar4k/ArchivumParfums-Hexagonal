<?php

declare(strict_types=1);

use Database\Seeders\BusinessesSeeder;
use Database\Seeders\PilotCatalogSeeder;
use Src\Catalog\Perfumes\Infrastructure\Persistence\Models\PerfumeModel;
use Src\Catalog\Perfumes\Infrastructure\Persistence\Models\PerfumeNoteModel;
use Src\Catalog\Perfumes\Infrastructure\Persistence\Models\PerfumeTagModel;
use Src\Catalog\Perfumes\Infrastructure\Persistence\Models\PerfumeVariantModel;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\Support\Shared\ScopesQueriesToBusiness;

use function Pest\Laravel\seed;

pest()->use(RefreshDatabaseAsOwner::class, ScopesQueriesToBusiness::class);

/** A catalog file of two perfumes, with and without photo, in the format of the real one. */
function smallPilotCatalog(): string
{
    $path = tempnam(sys_get_temp_dir(), 'archivum-catalog-');
    file_put_contents($path, json_encode([
        [
            'brand' => 'Casa Prueba', 'name' => 'Ámbar de Prueba', 'concentration' => 'EDP', 'gender' => 'unisex',
            'description' => 'Ámbar cálido para la noche.', 'is_active' => true, 'photo' => 'perfumes/ambar-de-prueba.png',
            'notes' => ['top' => ['Bergamota'], 'heart' => ['Rosa'], 'base' => ['Ámbar']],
            'tags' => ['time_of_day' => ['night'], 'climate' => ['cold'], 'occasion' => ['Cena']],
            'variants' => [['ml' => 50, 'price' => 45000, 'is_active' => true], ['ml' => 100, 'price' => 65000, 'is_active' => true]],
        ],
        [
            'brand' => 'Casa Prueba', 'name' => 'Sin Foto', 'is_active' => true,
            'notes' => ['top' => [], 'heart' => ['Lavanda'], 'base' => []],
            'tags' => ['time_of_day' => [], 'climate' => [], 'occasion' => []],
            'variants' => [['ml' => 30, 'price' => 20000, 'is_active' => true]],
        ],
    ], JSON_THROW_ON_ERROR));

    return $path;
}

beforeEach(function () {
    seed(BusinessesSeeder::class);
    $this->catalog = smallPilotCatalog();
    $this->scopeQueriesTo(BusinessesSeeder::DEMO_ARCHIVUMPARFUMS_ID);
});

afterEach(function () {
    unlink($this->catalog);
});

it('loads the perfumes, notes, tags and presentations of the file into the pilot store', function () {
    config(['filesystems.disks.r2.url' => 'https://fotos.example.test']);

    app(PilotCatalogSeeder::class)->seedFrom($this->catalog);

    $perfume = PerfumeModel::query()->where('name', 'Ámbar de Prueba')->firstOrFail();
    expect(PerfumeModel::query()->count())->toBe(2)
        ->and($perfume->image_path)->toBe('r2:https://fotos.example.test/perfumes/ambar-de-prueba.png')
        ->and(PerfumeVariantModel::query()->where('perfume_id', $perfume->id)->orderBy('size_ml')->pluck('sale_price_override')->all())->toBe([45000, 65000])
        ->and(PerfumeNoteModel::query()->where('perfume_id', $perfume->id)->count())->toBe(3)
        ->and(PerfumeTagModel::query()->where('perfume_id', $perfume->id)->count())->toBe(3)
        ->and(PerfumeModel::query()->where('name', 'Sin Foto')->value('image_path'))->toBeNull();
});

it('repeats nothing when it runs again', function () {
    $seeder = app(PilotCatalogSeeder::class);

    $seeder->seedFrom($this->catalog);
    $seeder->seedFrom($this->catalog);

    expect(PerfumeModel::query()->count())->toBe(2)
        ->and(PerfumeVariantModel::query()->count())->toBe(3);
});

it('leaves the photos out without R2_PUBLIC_URL and completes them on a later run', function () {
    config(['filesystems.disks.r2.url' => '']);
    $seeder = app(PilotCatalogSeeder::class);

    $seeder->seedFrom($this->catalog);
    expect(PerfumeModel::query()->whereNotNull('image_path')->count())->toBe(0);

    config(['filesystems.disks.r2.url' => 'https://fotos.example.test/']);
    $seeder->seedFrom($this->catalog);

    expect(PerfumeModel::query()->where('name', 'Ámbar de Prueba')->value('image_path'))
        ->toBe('r2:https://fotos.example.test/perfumes/ambar-de-prueba.png');
});

it('keeps the photo of a perfume that already has one', function () {
    config(['filesystems.disks.r2.url' => 'https://fotos.example.test']);
    $seeder = app(PilotCatalogSeeder::class);
    $seeder->seedFrom($this->catalog);
    PerfumeModel::query()->where('name', 'Ámbar de Prueba')->update(['image_path' => 'perfumes/subida-a-mano.png']);

    $seeder->seedFrom($this->catalog);

    expect(PerfumeModel::query()->where('name', 'Ámbar de Prueba')->value('image_path'))->toBe('perfumes/subida-a-mano.png');
});

it('ships a real catalog file with presentations and no photo origin', function () {
    $contents = (string) file_get_contents(PilotCatalogSeeder::CATALOG_FILE);
    $catalog = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);

    expect($catalog)->not->toBeEmpty()
        ->and(str_contains($contents, 'r2:'))->toBeFalse();

    foreach ($catalog as $perfume) {
        expect($perfume['photo'] ?? 'perfumes/')->toStartWith('perfumes/')
            ->and($perfume['brand'])->toBeString()->not->toBe('')
            ->and($perfume['name'])->toBeString()->not->toBe('')
            ->and($perfume['variants'])->not->toBeEmpty();

        foreach ($perfume['variants'] as $variant) {
            expect($variant['ml'])->toBeGreaterThan(0)
                ->and($variant['price'])->toBeGreaterThan(0);
        }
    }
});
