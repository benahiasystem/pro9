<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - order_code: varchar(20)
 * - external_id: char(36)
 * - customer: json
 * - shipping_address: varchar(255)
 * - items: json
 * - total: numeric(30,12)
 * - total_discount: numeric(30,12)
 * - discount_coupon_code: varchar(255)
 * - discount_coupon_id: numeric(20,0)
 * - stock_discounted: boolean
 * - reference_payment: varchar(255)
 * - document_external_id: char(36)
 * - number_document: varchar(255)
 * - status_order_id: bigint
 * - payment_status_order_id: bigint
 * - shipping_status_order_id: bigint
 * - tracking_code: varchar(120)
 * - purchase: json
 * - created_at: timestamp
 * - updated_at: timestamp
 * - deleted_at: timestamp
 * - apply_restaurant: boolean
 * - calculation_snapshot: json
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('order_code', 20)->collation('sab_ci_ai')->nullable();
            $table->char('external_id', 36)->collation('sab_ci_ai');
            $table->json('customer');
            $table->string('shipping_address', 255)->collation('sab_ci_ai')->nullable();
            $table->json('items');
            $table->decimal('total', 30, 12);
            $table->decimal('total_discount', 30, 12)->default('0.00');
            $table->string('discount_coupon_code', 255)->collation('sab_ci_ai')->nullable();
            $table->decimal('discount_coupon_id', 20, 0)->nullable();
            $table->boolean('stock_discounted')->default(false);
            $table->string('reference_payment', 255)->collation('sab_ci_ai');
            $table->char('document_external_id', 36)->collation('sab_ci_ai')->nullable();
            $table->string('number_document', 255)->collation('sab_ci_ai')->nullable();
            $table->bigInteger('status_order_id')->nullable();
            $table->bigInteger('payment_status_order_id')->nullable();
            $table->bigInteger('shipping_status_order_id')->nullable();
            $table->string('tracking_code', 120)->collation('sab_ci_ai')->nullable();
            $table->json('purchase')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->boolean('apply_restaurant')->nullable()->default(false);
            $table->json('calculation_snapshot')->nullable()->comment('Exact decimal fractions v1');
            $table->unique(['order_code'], 'orders_order_code_unique');
            $table->index(['status_order_id'], 'orders_status_order_id_foreign');
            $table->index(['payment_status_order_id'], 'orders_payment_status_order_id_foreign');
            $table->index(['shipping_status_order_id'], 'orders_shipping_status_order_id_foreign');
        });
        DB::statement('ALTER TABLE "orders" ADD CONSTRAINT "orders_discount_coupon_id_range" CHECK ("discount_coupon_id" BETWEEN 0 AND 18446744073709551615)');
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
