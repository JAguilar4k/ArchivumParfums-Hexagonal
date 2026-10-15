<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Create the tenants table, with the profile and the settings of each business. */
    public function up(): void
    {
        Schema::create('businesses', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('uuidv7()'));
            $table->string('name', 100);
            $table->string('slug', 100)->unique();
            $table->string('logo_path', 255)->nullable();
            $table->string('timezone', 64)->default('America/Costa_Rica');
            $table->string('location_text', 255)->default('');
            $table->jsonb('settings')->default(DB::raw("'{}'::jsonb"));
            $table->timestampsTz();
        });

        DB::statement(<<<'SQL'
            ALTER TABLE businesses
                ADD CONSTRAINT businesses_name_check CHECK (btrim(name) <> ''),
                ADD CONSTRAINT businesses_slug_check CHECK (slug ~ '^[a-z0-9-]+$'),
                ADD CONSTRAINT businesses_settings_check CHECK (jsonb_typeof(settings) = 'object')
            SQL);
    }

    /** Drop the tenants table. */
    public function down(): void
    {
        Schema::dropIfExists('businesses');
    }
};
