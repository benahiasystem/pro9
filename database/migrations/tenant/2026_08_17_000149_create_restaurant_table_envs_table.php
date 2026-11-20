<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - name: varchar(255)
 * - active: boolean
 * - tables_quantity: integer
 * - is_delivery: boolean
 * - is_takeaway: boolean
 * - can_edit: boolean
 * - can_deactivate: boolean
 * - can_delete: boolean
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restaurant_table_envs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 255)->collation('sab_ci_ai');
            $table->boolean('active');
            $table->integer('tables_quantity');
            $table->boolean('is_delivery')->default(false);
            $table->boolean('is_takeaway')->default(false);
            $table->boolean('can_edit')->default(true);
            $table->boolean('can_deactivate')->default(true);
            $table->boolean('can_delete')->default(true);
        });
        DB::statement('ALTER TABLE "restaurant_table_envs" ADD CONSTRAINT "restaurant_table_envs_tables_quantity_range" CHECK ("tables_quantity" BETWEEN -2147483648 AND 2147483647)');
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_table_envs');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
