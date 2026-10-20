<?php

declare(strict_types=1);

namespace Src\Businesses\Profile\Infrastructure\Persistence\Repositories;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Src\Businesses\Profile\Domain\Contracts\BusinessRepository;
use Src\Businesses\Profile\Domain\Entities\Business;
use Src\Businesses\Profile\Domain\Exceptions\SlugAlreadyInUseException;
use Src\Businesses\Profile\Domain\ValueObjects\BusinessSettings;
use Src\Businesses\Profile\Infrastructure\Persistence\Models\BusinessModel;
use Src\Shared\Domain\ValueObjects\BusinessId;

final readonly class EloquentBusinessRepository implements BusinessRepository
{
    /** Generate a time-ordered UUID for a new business. */
    public function nextId(): BusinessId
    {
        return new BusinessId((string) Str::uuid7());
    }

    /** Insert or update the business inside a savepoint and translate a repeated slug. */
    public function save(Business $business): void
    {
        try {
            DB::transaction(fn () => BusinessModel::query()->updateOrCreate(
                ['id' => $business->id->value],
                [
                    'name' => $business->name->value,
                    'slug' => $business->slug->value,
                    'timezone' => $business->timezone->value,
                    'location_text' => $business->appointmentPlace?->value,
                    // As an object, so a business without changes stores {} instead of a JSON array.
                    'settings' => (object) $this->storedSettings($business->settings),
                ],
            ));
        } catch (UniqueConstraintViolationException) {
            throw SlugAlreadyInUseException::create();
        }
    }

    /**
     * Keep, in snake_case, only the settings that differ from the defaults, so a business follows any later change of a default.
     *
     * @return array<string, mixed>
     */
    private function storedSettings(BusinessSettings $settings): array
    {
        $defaults = get_object_vars(BusinessSettings::defaults());
        $changed = array_filter(
            get_object_vars($settings),
            fn (mixed $value, string $name): bool => $value !== $defaults[$name],
            ARRAY_FILTER_USE_BOTH,
        );

        return array_combine(array_map(Str::snake(...), array_keys($changed)), $changed);
    }
}
