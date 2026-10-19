<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Src\Businesses\Profile\Domain\ValueObjects\BusinessTimezone;
use Src\Businesses\Profile\Infrastructure\Persistence\Models\BusinessModel;

final class BusinessesSeeder extends Seeder
{
    public const string DEMO_ARCHIVUMPARFUMS_ID = '0192f0c4-0000-7000-8000-000000000001';

    public const string DEMO_ESENCIAS_DEL_NORTE_ID = '0192f0c4-0000-7000-8000-000000000002';

    /**
     * Fictitious businesses by identifier: name, slug and appointment place. ArchivumParfums is the pilot; the
     * second one shows the isolation between businesses.
     *
     * @var array<string, array{string, string, string}>
     */
    private const array BUSINESSES = [
        self::DEMO_ARCHIVUMPARFUMS_ID => ['ArchivumParfums', 'archivumparfums', 'Escazú, San Rafael, 200 m norte del parque central'],
        self::DEMO_ESENCIAS_DEL_NORTE_ID => ['Esencias del Norte', 'esencias-del-norte', 'Ciudad Quesada, 100 m sur del mercado'],
    ];

    /** Seed the demo businesses with the default time zone and settings. */
    public function run(): void
    {
        foreach (self::BUSINESSES as $id => [$name, $slug, $place]) {
            BusinessModel::query()->updateOrCreate(
                ['id' => $id],
                [
                    'name' => $name,
                    'slug' => $slug,
                    'timezone' => BusinessTimezone::DEFAULT,
                    'location_text' => $place,
                    // As an object, as the repository stores it, so the business stores {} instead of a JSON array.
                    'settings' => (object) [],
                ],
            );
        }
    }
}
