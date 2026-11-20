<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: varchar(255)
 * - active: boolean
 * - symbol: varchar(255)
 * - description: varchar(255)
 * - hka_code: varchar(3)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cat_unit_types', function (Blueprint $table) {
            $table->string('id', 255)->collation('pro9_ci_ai');
            $table->boolean('active');
            $table->string('symbol', 255)->collation('pro9_ci_ai')->nullable();
            $table->string('description', 255)->collation('pro9_ci_ai');
            $table->string('hka_code', 3)->collation('pro9_ci_ai');
            $table->index(['id'], 'cat_unit_types_id_index');
            $table->unique(['id'], 'cat_unit_types_id_pg_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cat_unit_types');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
