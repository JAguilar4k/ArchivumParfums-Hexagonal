<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Src\Businesses\Profile\Infrastructure\Persistence\Models\BusinessModel;

/**
 * @extends Factory<BusinessModel>
 */
final class BusinessFactory extends Factory
{
    protected $model = BusinessModel::class;

    /**
     * Default state of a fictitious business with the default settings.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = 'Perfumería '.fake()->unique()->lastName();

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'timezone' => 'America/Costa_Rica',
            'location_text' => null,
            // As an object, as the repository stores it, so the business stores {} instead of a JSON array.
            'settings' => (object) [],
        ];
    }
}
