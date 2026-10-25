<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('uuidv7()'));
            $table->foreignUuid('business_id')->constrained();
            $table->uuid('order_id');
            $table->string('purpose', 16);
            $table->integer('amount');
            $table->string('gateway', 16);
            $table->string('method', 24)->default('card');
            $table->string('status', 24)->default('pending');
            $table->string('charged_currency', 3)->default('CRC');
            $table->integer('charged_amount')->default(0);
            $table->decimal('exchange_rate', 18, 6)->nullable();
            $table->string('provider_reference')->nullable();
            $table->string('checkout_reference')->nullable();
            $table->text('checkout_url')->nullable();
            $table->string('capture_reference')->nullable();
            $table->integer('refunded_amount')->default(0);
            $table->uuid('recorded_by')->nullable();
            $table->string('manual_method', 16)->nullable();
            $table->string('manual_reference')->nullable();
            $table->timestampsTz();
            $table->unique(['id', 'business_id']);
            $table->unique(['business_id', 'gateway', 'provider_reference']);
            $table->foreign(['order_id', 'business_id'])->references(['id', 'business_id'])->on('orders');
            $table->foreign(['recorded_by', 'business_id'])->references(['id', 'business_id'])->on('members');
            $table->index(['business_id', 'status']);
        });

        DB::unprepared(<<<'SQL'
            ALTER TABLE payments
                ADD CONSTRAINT payments_amount_check CHECK (amount > 0),
                ADD CONSTRAINT payments_gateway_check CHECK (gateway IN ('onvo', 'paypal', 'manual')),
                ADD CONSTRAINT payments_purpose_check CHECK (purpose IN ('deposit', 'balance', 'full')),
                ADD CONSTRAINT payments_method_check CHECK (method IN ('card', 'sinpe_mobile')),
                ADD CONSTRAINT payments_status_check CHECK (status IN ('pending', 'succeeded', 'failed', 'expired', 'refunded', 'partially_refunded')),
                ADD CONSTRAINT payments_charged_currency_check CHECK (charged_currency IN ('CRC', 'USD')),
                ADD CONSTRAINT payments_charged_amount_check CHECK (charged_amount >= 0),
                ADD CONSTRAINT payments_refunded_amount_check CHECK (refunded_amount >= 0 AND refunded_amount <= amount),
                ADD CONSTRAINT payments_manual_reference_check CHECK (gateway <> 'manual' OR (recorded_by IS NOT NULL AND manual_reference IS NOT NULL AND manual_method IN ('sinpe', 'cash'))),
                ADD CONSTRAINT payments_exchange_rate_check CHECK (charged_currency <> 'USD' OR (exchange_rate IS NOT NULL AND exchange_rate > 0));
            CREATE UNIQUE INDEX payments_one_pending_purpose ON payments (business_id, order_id, purpose) WHERE status = 'pending';
            SQL);

        Schema::create('refunds', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('uuidv7()'));
            $table->foreignUuid('business_id')->constrained();
            $table->uuid('payment_id');
            $table->uuid('order_line_id')->nullable();
            $table->integer('amount');
            $table->string('status', 16)->default('pending');
            $table->string('provider_reference')->nullable();
            $table->timestampTz('attempted_at')->nullable();
            $table->text('reason')->nullable();
            $table->timestampsTz();
            $table->unique(['id', 'business_id']);
            $table->foreign(['payment_id', 'business_id'])->references(['id', 'business_id'])->on('payments');
            $table->foreign(['order_line_id', 'business_id'])->references(['id', 'business_id'])->on('order_lines');
            $table->index(['business_id', 'status']);
        });
        DB::statement('ALTER TABLE refunds ADD CONSTRAINT refunds_amount_check CHECK (amount > 0)');
        DB::statement("ALTER TABLE refunds ADD CONSTRAINT refunds_status_check CHECK (status IN ('pending', 'succeeded', 'failed'))");

        Schema::create('webhook_events', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('uuidv7()'));
            $table->string('gateway', 16);
            $table->string('event_id');
            $table->string('event_type');
            $table->jsonb('payload');
            $table->timestampTz('processed_at')->nullable();
            $table->timestampsTz();
            $table->unique(['gateway', 'event_id']);
        });
        DB::statement("ALTER TABLE webhook_events ADD CONSTRAINT webhook_events_gateway_check CHECK (gateway IN ('onvo', 'paypal'))");

        foreach (['payments', 'refunds'] as $table) {
            DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE {$table} FORCE ROW LEVEL SECURITY");
            DB::statement("CREATE POLICY {$table}_business_isolation ON {$table} USING (business_id = NULLIF(current_setting('app.business_id', true), '')::uuid) WITH CHECK (business_id = NULLIF(current_setting('app.business_id', true), '')::uuid)");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
        Schema::dropIfExists('refunds');
        Schema::dropIfExists('payments');
    }
};
