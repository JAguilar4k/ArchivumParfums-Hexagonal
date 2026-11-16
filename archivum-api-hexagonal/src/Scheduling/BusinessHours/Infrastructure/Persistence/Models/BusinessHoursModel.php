<?php

declare(strict_types=1);

namespace Src\Scheduling\BusinessHours\Infrastructure\Persistence\Models;

use Database\Factories\BusinessHoursFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $business_id
 * @property int $weekday
 * @property string $opens_at
 * @property string $closes_at
 */
#[Table('business_hours')]
#[Fillable(['id', 'business_id', 'weekday', 'opens_at', 'closes_at'])]
#[UseFactory(BusinessHoursFactory::class)]
final class BusinessHoursModel extends Model
{
    /** @use HasFactory<BusinessHoursFactory> */
    use HasFactory, HasUuids;

    /**
     * Cast the weekday to a native integer. The local hours stay as the text PostgreSQL returns for a time.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'weekday' => 'integer',
        ];
    }
}
