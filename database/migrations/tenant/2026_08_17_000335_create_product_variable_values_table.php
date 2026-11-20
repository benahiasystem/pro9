<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - product_variable_id: bigint
 * - value: varchar(100)
 * - color: varchar(7)
 * - position: integer
 * - active: boolean
 * - created_at: timestamp
 * - updated_at: timestamp
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variable_values', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('product_variable_id');
            $table->string('value', 100)->collation('pro9_ci_ai');
            $table->string('color', 7)->collation('pro9_ci_ai')->nullable();
            $table->integer('position')->default('0');
            $table->boolean('active')->default(true);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['product_variable_id', 'value'], 'pvv_variable_value_unique');
        });
        DB::statement('ALTER TABLE "product_variable_values" ADD CONSTRAINT "product_variable_values_position_range" CHECK ("position" BETWEEN -2147483648 AND 2147483647)');
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variable_values');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
