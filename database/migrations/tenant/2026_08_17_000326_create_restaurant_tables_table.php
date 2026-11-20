<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - status: varchar(255)
 * - products: json
 * - total: numeric(30,12)
 * - personas: integer
 * - cliente: varchar(255)
 * - comentarios: varchar(255)
 * - label: varchar(255)
 * - shape: varchar(255)
 * - environment: varchar(255)
 * - original_environment: varchar(100)
 * - waiter: varchar(255)
 * - opening_date: timestamp
 * - order_status: varchar(255)
 * - group_id: bigint
 * - is_active: boolean
 * - is_paid: boolean
 * - delivery: json
 * - calculation_snapshot: json
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restaurant_tables', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('status', 255)->collation('pro9_ci_ai');
            $table->json('products')->nullable();
            $table->decimal('total', 30, 12);
            $table->integer('personas');
            $table->string('cliente', 255)->collation('pro9_ci_ai')->nullable();
            $table->string('comentarios', 255)->collation('pro9_ci_ai')->nullable();
            $table->string('label', 255)->collation('pro9_ci_ai');
            $table->string('shape', 255)->collation('pro9_ci_ai');
            $table->string('environment', 255)->collation('pro9_ci_ai');
            $table->string('original_environment', 100)->collation('pro9_ci_ai')->nullable()->comment('Ambiente original de la mesa (NULL si no ha sido movida)');
            $table->string('waiter', 255)->collation('pro9_ci_ai')->nullable();
            $table->timestamp('opening_date')->nullable();
            $table->string('order_status', 255)->collation('pro9_ci_ai')->default('pending');
            $table->bigInteger('group_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_paid')->default(false);
            $table->json('delivery')->nullable();
            $table->json('calculation_snapshot')->nullable()->comment('Exact decimal fractions v1');
            $table->index(['group_id'], 'restaurant_tables_group_id_foreign');
        });
        DB::statement('ALTER TABLE "restaurant_tables" ADD CONSTRAINT "restaurant_tables_personas_range" CHECK ("personas" BETWEEN -2147483648 AND 2147483647)');
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_tables');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
