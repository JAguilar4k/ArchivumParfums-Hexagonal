<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the user accounts table. An account has no business_id: the same customer account works in several
     * businesses, and the business of a staff member lives in members.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('uuidv7()'));
            $table->string('name', 120);
            $table->string('email', 255)->unique();
            $table->string('password', 255);
            $table->timestampsTz();
        });

        DB::statement("ALTER TABLE users ADD CONSTRAINT users_name_check CHECK (btrim(name) <> '')");
    }

    /** Drop the user accounts table. */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
