<?php

declare(strict_types=1);

namespace Src\Accounts\Users\Infrastructure\Persistence\Models;

use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $business_id
 * @property string $user_id
 * @property string|null $phone
 */
#[Table('customers')]
#[Fillable(['id', 'business_id', 'user_id', 'phone'])]
#[UseFactory(CustomerFactory::class)]
final class CustomerModel extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory, HasUuids;
}
