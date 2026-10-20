<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Refuse a second perfume with the same brand, name and concentration in one business.
     *
     * The three columns are compared ignoring case, and a missing concentration counts as an empty one, so
     * "Oud Wood" without concentration and "oud wood" without concentration collide, while the EDP and the EDT do not.
     * What makes a perfume repeated is a proposal still to be confirmed (#39).
     */
    public function up(): void
    {
        DB::statement(
            "CREATE UNIQUE INDEX perfumes_business_brand_name_unique ON perfumes (business_id, lower(brand), lower(name), lower(coalesce(concentration, '')))",
        );
    }

    /** Drop the unique index of the perfumes. */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS perfumes_business_brand_name_unique');
    }
};
