<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - title: varchar(255)
 * - discount_type: varchar(255)
 * - discount_value: numeric(30,12)
 * - start_date: timestamp
 * - end_date: timestamp
 * - sp_product_ids: json
 * - status: boolean
 * - sp_countdown: boolean
 * - sp_discount_price: boolean
 * - sp_purchase_count: boolean
 * - sp_views_count: boolean
 * - sp_stock_alert: boolean
 * - sp_rating: boolean
 * - sp_stock_threshold: bigint
 * - sp_views_min: integer
 * - sp_views_max: integer
 * - sp_purchase_min: integer
 * - sp_purchase_max: integer
 * - created_at: timestamp
 * - updated_at: timestamp
 * - calculation_snapshot: json
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ecommerce_campaigns', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('title', 255)->collation('pro9_ci_ai');
            $table->string('discount_type', 255)->collation('pro9_ci_ai')->default('percentage');
            $table->decimal('discount_value', 30, 12)->default('0.0000');
            $table->timestamp('start_date')->nullable();
            $table->timestamp('end_date')->nullable();
            $table->json('sp_product_ids')->nullable();
            $table->boolean('status')->default(true);
            $table->boolean('sp_countdown')->default(false);
            $table->boolean('sp_discount_price')->default(false);
            $table->boolean('sp_purchase_count')->default(false);
            $table->boolean('sp_views_count')->default(false);
            $table->boolean('sp_stock_alert')->default(false);
            $table->boolean('sp_rating')->default(false);
            $table->bigInteger('sp_stock_threshold')->default('10');
            $table->integer('sp_views_min')->default('10');
            $table->integer('sp_views_max')->default('50');
            $table->integer('sp_purchase_min')->default('5');
            $table->integer('sp_purchase_max')->default('30');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->json('calculation_snapshot')->nullable()->comment('Exact decimal fractions v1');
            $table->index(['status', 'end_date'], 'ecommerce_campaigns_status_end_idx');
        });
        DB::statement('ALTER TABLE "ecommerce_campaigns" ADD CONSTRAINT "ecommerce_campaigns_sp_stock_threshold_range" CHECK ("sp_stock_threshold" BETWEEN 0 AND 4294967295)');
        DB::statement('ALTER TABLE "ecommerce_campaigns" ADD CONSTRAINT "ecommerce_campaigns_sp_views_min_range" CHECK ("sp_views_min" BETWEEN -2147483648 AND 2147483647)');
        DB::statement('ALTER TABLE "ecommerce_campaigns" ADD CONSTRAINT "ecommerce_campaigns_sp_views_max_range" CHECK ("sp_views_max" BETWEEN -2147483648 AND 2147483647)');
        DB::statement('ALTER TABLE "ecommerce_campaigns" ADD CONSTRAINT "ecommerce_campaigns_sp_purchase_min_range" CHECK ("sp_purchase_min" BETWEEN -2147483648 AND 2147483647)');
        DB::statement('ALTER TABLE "ecommerce_campaigns" ADD CONSTRAINT "ecommerce_campaigns_sp_purchase_max_range" CHECK ("sp_purchase_max" BETWEEN -2147483648 AND 2147483647)');
    }

    public function down(): void
    {
        Schema::dropIfExists('ecommerce_campaigns');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
