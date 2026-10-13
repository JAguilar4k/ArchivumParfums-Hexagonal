<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Migrate with the schema owner and run the test with the application role.
 */
trait RefreshDatabaseAsOwner
{
    use RefreshDatabase {
        migrateFreshUsing as private frameworkMigrateFreshUsing;
    }

    /**
     * @return array<string, mixed>
     */
    protected function migrateFreshUsing(): array
    {
        return [...$this->frameworkMigrateFreshUsing(), '--database' => 'pgsql_admin'];
    }
}
