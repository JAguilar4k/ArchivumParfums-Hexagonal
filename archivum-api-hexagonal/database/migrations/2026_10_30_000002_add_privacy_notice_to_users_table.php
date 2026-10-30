<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Keep when a customer accepted the privacy notice and which version; accounts of the staff leave them empty. */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->timestampTz('privacy_accepted_at')->nullable();
            $table->string('privacy_notice_version', 20)->nullable();
        });
    }

    /** Drop the two columns. */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['privacy_accepted_at', 'privacy_notice_version']);
        });
    }
};
