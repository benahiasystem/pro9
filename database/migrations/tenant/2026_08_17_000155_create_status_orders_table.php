<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - description: varchar(30)
 * - color: varchar(7)
 * - sort_order: smallint
 * - is_initial: boolean
 * - is_final: boolean
 * - is_payment_status: boolean
 * - is_order_status: boolean
 * - is_shipping_status: boolean
 * - action_generate_document: boolean
 * - action_discount_stock: boolean
 * - action_mark_payment: boolean
 * - action_send_email: boolean
 * - action_notify_dispatch: boolean
 * - action_generate_remission: boolean
 * - action_free_reserved_stock: boolean
 * - action_block_returns: boolean
 * - action_void_order: boolean
 * - created_at: timestamp
 * - updated_at: timestamp
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('status_orders', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('description', 30)->collation('pro9_ci_ai');
            $table->string('color', 7)->collation('pro9_ci_ai')->nullable();
            $table->smallInteger('sort_order')->default('0');
            $table->boolean('is_initial')->default(false);
            $table->boolean('is_final')->default(false);
            $table->boolean('is_payment_status')->default(false);
            $table->boolean('is_order_status')->default(true);
            $table->boolean('is_shipping_status')->default(false);
            $table->boolean('action_generate_document')->default(false);
            $table->boolean('action_discount_stock')->default(false);
            $table->boolean('action_mark_payment')->default(false);
            $table->boolean('action_send_email')->default(false);
            $table->boolean('action_notify_dispatch')->default(false);
            $table->boolean('action_generate_remission')->default(false);
            $table->boolean('action_free_reserved_stock')->default(false);
            $table->boolean('action_block_returns')->default(false);
            $table->boolean('action_void_order')->default(false);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
        DB::statement('ALTER TABLE "status_orders" ADD CONSTRAINT "status_orders_sort_order_range" CHECK ("sort_order" BETWEEN 0 AND 255)');
    }

    public function down(): void
    {
        Schema::dropIfExists('status_orders');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
