<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Create tenant-scoped orders, frozen lines and the movement reference. */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('uuidv7()'));
            $table->foreignUuid('business_id')->constrained()->cascadeOnDelete();
            $table->uuid('customer_id');
            $table->string('status', 30);
            $table->integer('subtotal');
            $table->integer('due_now');
            $table->integer('paid')->default(0);
            $table->integer('balance');
            $table->smallInteger('deposit_percent')->default(50);
            $table->string('delivery_method', 20)->nullable();
            $table->timestampTz('expires_at');
            $table->timestampTz('cancelled_at')->nullable();
            $table->timestampsTz();
            $table->unique(['id', 'business_id']);
            $table->foreign(['customer_id', 'business_id'])->references(['id', 'business_id'])->on('customers');
            $table->index(['business_id', 'customer_id', 'created_at']);
            $table->index(['business_id', 'status', 'expires_at']);
        });

        Schema::create('order_lines', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('uuidv7()'));
            $table->foreignUuid('business_id')->constrained()->cascadeOnDelete();
            $table->uuid('order_id');
            $table->string('kind', 20);
            $table->uuid('perfume_variant_id')->nullable();
            $table->uuid('decant_bottle_id')->nullable();
            $table->integer('ml')->nullable();
            $table->integer('quantity');
            $table->integer('unit_price');
            $table->string('status', 20);
            $table->uuid('decant_pack_id')->nullable();
            $table->uuid('purchase_order_line_id')->nullable();
            $table->timestampsTz();
            $table->unique(['id', 'business_id']);
            $table->foreign(['order_id', 'business_id'])->references(['id', 'business_id'])->on('orders')->cascadeOnDelete();
            $table->foreign(['perfume_variant_id', 'business_id'])->references(['id', 'business_id'])->on('perfume_variants');
            $table->foreign(['decant_bottle_id', 'business_id'])->references(['id', 'business_id'])->on('decant_bottles');
        });

        foreach (['subtotal', 'due_now', 'paid', 'balance'] as $column) {
            DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_{$column}_check CHECK ({$column} >= 0)");
        }

        DB::unprepared(<<<'SQL'
            ALTER TABLE orders
                ADD CONSTRAINT orders_status_check CHECK (status IN ('pending_payment','confirmed','ordered','ready','delivered','cancelled','expired')),
                ADD CONSTRAINT orders_deposit_percent_check CHECK (deposit_percent BETWEEN 0 AND 100),
                ADD CONSTRAINT orders_delivery_method_check CHECK (delivery_method IN ('in_person','pickup'));
            ALTER TABLE order_lines
                ADD CONSTRAINT order_lines_unit_price_check CHECK (unit_price >= 0),
                ADD CONSTRAINT order_lines_quantity_check CHECK (quantity > 0),
                ADD CONSTRAINT order_lines_ml_check CHECK (ml > 0),
                ADD CONSTRAINT order_lines_kind_check CHECK (
                    (kind = 'bottle' AND perfume_variant_id IS NOT NULL AND decant_bottle_id IS NULL AND ml IS NULL
                        AND decant_pack_id IS NULL AND status IN ('pending','ordered','received','unavailable'))
                    OR
                    (kind = 'decant' AND decant_bottle_id IS NOT NULL AND ml IS NOT NULL AND ml > 0 AND quantity = 1
                        AND status IN ('reserved','prepared','released'))
                );
            ALTER TABLE decant_movements ADD CONSTRAINT decant_movements_order_line_id_business_id_foreign
                FOREIGN KEY (order_line_id, business_id) REFERENCES order_lines (id, business_id);
            SQL);

        foreach (['orders', 'order_lines'] as $table) {
            DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE {$table} FORCE ROW LEVEL SECURITY");
            DB::statement("CREATE POLICY {$table}_business_isolation ON {$table} USING (business_id = NULLIF(current_setting('app.business_id', true), '')::uuid) WITH CHECK (business_id = NULLIF(current_setting('app.business_id', true), '')::uuid)");
        }

        $owner = config('database.connections.pgsql_admin.username');
        $quotedOwner = '"'.str_replace('"', '""', is_string($owner) ? $owner : '').'"';
        DB::statement("CREATE POLICY orders_definer_read ON orders FOR SELECT TO {$quotedOwner} USING (true)");
        $this->resourceFunction(withOrders: true);
    }

    /** Restore the customer-only resolver before removing the resource tables. */
    public function down(): void
    {
        $this->resourceFunction(withOrders: false);
        DB::statement('ALTER TABLE decant_movements DROP CONSTRAINT decant_movements_order_line_id_business_id_foreign');
        Schema::dropIfExists('order_lines');
        Schema::dropIfExists('orders');
    }

    /** Keep the definer function a closed list of supported customer resources. */
    private function resourceFunction(bool $withOrders): void
    {
        $orderBranch = $withOrders ? <<<'SQL'
            WHEN 'order' THEN
                RETURN (SELECT o.business_id FROM public.orders o JOIN public.customers c
                    ON c.id = o.customer_id AND c.business_id = o.business_id
                    WHERE o.id = p_id AND c.user_id = p_user_id);
            SQL : '';

        DB::unprepared(<<<SQL
            CREATE OR REPLACE FUNCTION fn_customer_resource_business(p_type text, p_id uuid, p_user_id uuid)
                RETURNS uuid LANGUAGE plpgsql STABLE SECURITY DEFINER SET search_path = ''
            AS \$\$
            BEGIN
                CASE p_type
                    WHEN 'customer' THEN
                        RETURN (SELECT c.business_id FROM public.customers c WHERE c.id = p_id AND c.user_id = p_user_id);
                    {$orderBranch}
                    ELSE RAISE EXCEPTION 'Unknown resource type of a customer: %', p_type USING ERRCODE = 'invalid_parameter_value';
                END CASE;
            END;
            \$\$;
            SQL);
    }
};
