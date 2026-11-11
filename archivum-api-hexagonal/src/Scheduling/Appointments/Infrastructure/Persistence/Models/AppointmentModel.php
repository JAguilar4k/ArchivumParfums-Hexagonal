<?php

declare(strict_types=1);

namespace Src\Scheduling\Appointments\Infrastructure\Persistence\Models;

use Database\Factories\AppointmentModelFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * The row of one appointment. The aggregate of the domain never sees this class: the repository maps between them.
 *
 * @property string $id
 * @property string $business_id
 * @property string $service_id
 * @property string $customer_id
 * @property string $member_id
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property Carbon $blocked_until
 * @property string $status
 * @property Carbon|null $hold_expires_at
 * @property Carbon|null $cancelled_at
 * @property string|null $cancel_reason
 */
#[Table('appointments')]
#[Fillable([
    'id',
    'business_id',
    'service_id',
    'customer_id',
    'member_id',
    'starts_at',
    'ends_at',
    'blocked_until',
    'status',
    'hold_expires_at',
    'cancelled_at',
    'cancel_reason',
])]
#[UseFactory(AppointmentModelFactory::class)]
final class AppointmentModel extends Model
{
    /** @use HasFactory<AppointmentModelFactory> */
    use HasFactory, HasUuids;

    /** Read the instants of the appointment as dates and keep the state as the string of the enum. */
    protected function casts(): array
    {
        return [
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'blocked_until' => 'immutable_datetime',
            'hold_expires_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
        ];
    }

    /**
     * The perfumes the appointment tries, as the repository loads them with the aggregate.
     *
     * @return HasMany<AppointmentPerfumeModel, $this>
     */
    public function perfumes(): HasMany
    {
        return $this->hasMany(AppointmentPerfumeModel::class, 'appointment_id');
    }
}
