<?php

declare(strict_types=1);

namespace Src\Scheduling\Appointments\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * One perfume an appointment tries. It carries the business so the composite foreign keys never cross a tenant.
 *
 * @property string $id
 * @property string $business_id
 * @property string $appointment_id
 * @property string $perfume_id
 */
#[Table('appointment_perfumes')]
#[Fillable(['id', 'business_id', 'appointment_id', 'perfume_id'])]
final class AppointmentPerfumeModel extends Model {}
