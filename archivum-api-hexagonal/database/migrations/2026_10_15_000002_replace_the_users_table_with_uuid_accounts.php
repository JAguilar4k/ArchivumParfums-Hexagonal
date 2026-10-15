<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Replace the framework's users table with accounts identified by UUID v7.
     *
     * An account has no business_id: the same customer account works in several businesses,
     * and the business of a staff member lives in members.
     */
    public function up(): void
    {
        Schema::drop('users');

        Schema::create('users', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('uuidv7()'));
            $table->string('name', 120);
            $table->string('email', 255);
            $table->string('password', 255);
            $table->timestampsTz();
        });

        DB::statement('CREATE UNIQUE INDEX users_email_unique ON users (lower(email))');
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_name_check CHECK (btrim(name) <> '')");
        DB::statement('ALTER TABLE sessions ALTER COLUMN user_id TYPE uuid USING NULL');
    }

    /** Restore the framework's users table. */
    public function down(): void
    {
        DB::statement('ALTER TABLE sessions ALTER COLUMN user_id TYPE bigint USING NULL');

        Schema::drop('users');

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });
    }
};
