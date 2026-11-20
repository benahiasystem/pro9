<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - code: varchar(20)
 * - type: varchar(255)
 * - amount: numeric(30,12)
 * - has_purchase_limits: boolean
 * - min_amount: numeric(30,12)
 * - max_amount: numeric(30,12)
 * - has_usage_limits: boolean
 * - max_total_uses: bigint
 * - uses_count: bigint
 * - max_uses_per_customer: bigint
 * - expires_at: timestamp
 * - free_shipping: boolean
 * - active: boolean
 * - created_at: timestamp
 * - updated_at: timestamp
 * - deleted_at: timestamp
 * - calculation_snapshot: json
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discount_coupons', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('code', 20)->collation('pro9_ci_ai');
            $table->string('type', 255)->collation('pro9_ci_ai')->default('percentage');
            $table->decimal('amount', 30, 12)->default('0.00');
            $table->boolean('has_purchase_limits')->default(false);
            $table->decimal('min_amount', 30, 12)->nullable();
            $table->decimal('max_amount', 30, 12)->nullable();
            $table->boolean('has_usage_limits')->default(false);
            $table->bigInteger('max_total_uses')->nullable();
            $table->bigInteger('uses_count')->default('0');
            $table->bigInteger('max_uses_per_customer')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('free_shipping')->default(false);
            $table->boolean('active')->default(true);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->json('calculation_snapshot')->nullable()->comment('Exact decimal fractions v1');
            $table->unique(['code'], 'discount_coupons_code_unique');
        });
        DB::statement('ALTER TABLE "discount_coupons" ADD CONSTRAINT "discount_coupons_type_check" CHECK ("type" IN (\'percentage\',\'fixed\'))');
        DB::statement('ALTER TABLE "discount_coupons" ADD CONSTRAINT "discount_coupons_max_total_uses_range" CHECK ("max_total_uses" BETWEEN 0 AND 4294967295)');
        DB::statement('ALTER TABLE "discount_coupons" ADD CONSTRAINT "discount_coupons_uses_count_range" CHECK ("uses_count" BETWEEN 0 AND 4294967295)');
        DB::statement('ALTER TABLE "discount_coupons" ADD CONSTRAINT "discount_coupons_max_uses_per_customer_range" CHECK ("max_uses_per_customer" BETWEEN 0 AND 4294967295)');
    }

    public function down(): void
    {
        Schema::dropIfExists('discount_coupons');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
