<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Remember the key a customer sent with the purchase of a pack (#145), so the same request sent again answers the
     * pack already bought. Two requests with the same key at once collide on the unique index, and the second one
     * answers the pack of the first. A purchase without a key leaves it empty, and empty keys never collide.
     */
    public function up(): void
    {
        Schema::table('decant_packs', function (Blueprint $table): void {
            $table->string('idempotency_key', 100)->nullable();
            $table->unique(['business_id', 'customer_id', 'idempotency_key']);
        });
    }

    /** Forget the keys. */
    public function down(): void
    {
        Schema::table('decant_packs', function (Blueprint $table): void {
            $table->dropUnique(['business_id', 'customer_id', 'idempotency_key']);
            $table->dropColumn('idempotency_key');
        });
    }
};
