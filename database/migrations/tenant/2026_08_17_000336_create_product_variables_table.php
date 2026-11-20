<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - name: varchar(100)
 * - value_type: varchar(10)
 * - active: boolean
 * - created_at: timestamp
 * - updated_at: timestamp
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variables', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 100)->collation('pro9_ci_ai');
            $table->string('value_type', 10)->collation('pro9_ci_ai')->default('list');
            $table->boolean('active')->default(true);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['name'], 'product_variables_name_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variables');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
