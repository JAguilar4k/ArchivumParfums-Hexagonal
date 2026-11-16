<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the appointments of a business and the perfumes each one tries, with the columns of section 7 of the
     * specification. The row-level policy of both tables lives in its own migration, as in the catalog.
     */
    public function up(): void
    {
        // The exclusion below compares business_id and member_id with `=` inside a GiST index. #52 already created the
        // extension for the opening hours, so IF NOT EXISTS keeps this safe and no down() removes it.
        DB::statement('CREATE EXTENSION IF NOT EXISTS btree_gist');

        Schema::create('appointments', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('uuidv7()'));
            $table->foreignUuid('business_id')->constrained()->cascadeOnDelete();
            $table->uuid('service_id');
            $table->uuid('customer_id');
            $table->uuid('member_id');
            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at');
            // The end plus the margin of the service, stored so the exclusion never depends on the service in force.
            $table->timestampTz('blocked_until');
            $table->string('status', 20)->default('confirmed');
            $table->timestampTz('hold_expires_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->string('cancel_reason', 200)->nullable();
            $table->timestampsTz();

            // Destination of the composite foreign key of appointment_perfumes.
            $table->unique(['id', 'business_id']);
            // Nobody books the service, the customer or the person of another business.
            $table->foreign(['service_id', 'business_id'])->references(['id', 'business_id'])->on('services');
            $table->foreign(['customer_id', 'business_id'])->references(['id', 'business_id'])->on('customers');
            $table->foreign(['member_id', 'business_id'])->references(['id', 'business_id'])->on('members');
            // The agenda of one day and the appointments of one customer.
            $table->index(['business_id', 'starts_at']);
            $table->index(['business_id', 'customer_id', 'starts_at']);
        });

        DB::statement('ALTER TABLE appointments ADD CONSTRAINT appointments_ends_at_check CHECK (ends_at > starts_at)');
        DB::statement('ALTER TABLE appointments ADD CONSTRAINT appointments_blocked_until_check CHECK (blocked_until >= ends_at)');
        DB::statement(<<<'SQL'
            ALTER TABLE appointments ADD CONSTRAINT appointments_status_check
                CHECK (status IN ('held', 'confirmed', 'cancelled', 'completed', 'no_show', 'expired'))
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE appointments ADD CONSTRAINT appointments_hold_expires_at_check
                CHECK (status <> 'held' OR hold_expires_at IS NOT NULL)
            SQL);
        // One person never holds two active appointments over the same instant. The range is half open, so an
        // appointment may start exactly when the previous one stops being blocked, margin included.
        DB::statement(<<<'SQL'
            ALTER TABLE appointments ADD CONSTRAINT appointments_member_slot_exclusion
                EXCLUDE USING gist (
                    business_id WITH =,
                    member_id WITH =,
                    tstzrange(starts_at, blocked_until) WITH &&
                ) WHERE (status IN ('held', 'confirmed'))
            SQL);

        Schema::create('appointment_perfumes', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('uuidv7()'));
            $table->foreignUuid('business_id')->constrained()->cascadeOnDelete();
            $table->uuid('appointment_id');
            $table->uuid('perfume_id');
            $table->timestampsTz();

            // A perfume is tried once per appointment, and only from the catalog of the same business.
            $table->unique(['business_id', 'appointment_id', 'perfume_id']);
            $table->foreign(['appointment_id', 'business_id'])->references(['id', 'business_id'])->on('appointments')->cascadeOnDelete();
            $table->foreign(['perfume_id', 'business_id'])->references(['id', 'business_id'])->on('perfumes');
        });
    }

    /** Drop both tables with their constraints, foreign keys and indexes; btree_gist stays, as #52 needs it. */
    public function down(): void
    {
        Schema::dropIfExists('appointment_perfumes');
        Schema::dropIfExists('appointments');
    }
};
