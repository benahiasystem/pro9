<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: varchar(255)
 * - active: boolean
 * - exportation: boolean
 * - description: varchar(255)
 * - incoterm: varchar(3)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cat_operation_types', function (Blueprint $table) {
            $table->string('id', 255)->collation('pro9_ci_ai');
            $table->boolean('active');
            $table->boolean('exportation');
            $table->string('description', 255)->collation('pro9_ci_ai');
            $table->string('incoterm', 3)->collation('pro9_ci_ai')->nullable();
            $table->index(['id'], 'cat_operation_types_id_index');
            $table->unique(['id'], 'cat_operation_types_id_pg_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cat_operation_types');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
