<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: char(2)
 * - description: varchar(255)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('state_types', function (Blueprint $table) {
            $table->char('id', 2)->collation('pro9_ci_ai');
            $table->string('description', 255)->collation('pro9_ci_ai');
            $table->index(['id'], 'state_types_id_index');
            $table->unique(['id'], 'state_types_id_pg_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('state_types');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
