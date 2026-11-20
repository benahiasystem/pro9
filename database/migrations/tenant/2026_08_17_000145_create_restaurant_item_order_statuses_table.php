<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - table_id: bigint
 * - item_id: bigint
 * - item: json
 * - quantity: integer
 * - note: varchar(255)
 * - status: integer
 * - status_description: varchar(255)
 * - created_at: timestamp
 * - updated_at: timestamp
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restaurant_item_order_statuses', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('table_id');
            $table->bigInteger('item_id');
            $table->json('item')->nullable();
            $table->integer('quantity');
            $table->string('note', 255)->collation('pro9_ci_ai')->nullable();
            $table->integer('status');
            $table->string('status_description', 255)->collation('pro9_ci_ai')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
        DB::statement('ALTER TABLE "restaurant_item_order_statuses" ADD CONSTRAINT "restaurant_item_order_statuses_quantity_range" CHECK ("quantity" BETWEEN -2147483648 AND 2147483647)');
        DB::statement('ALTER TABLE "restaurant_item_order_statuses" ADD CONSTRAINT "restaurant_item_order_statuses_status_range" CHECK ("status" BETWEEN -2147483648 AND 2147483647)');
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_item_order_statuses');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
