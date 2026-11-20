<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: char(2)
 * - description: varchar(255)
 * - active: boolean
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->char('id', 2)->collation('pro9_ci_ai');
            $table->string('description', 255)->collation('pro9_ci_ai');
            $table->boolean('active')->default(true);
            $table->index(['id'], 'departments_id_index');
            $table->unique(['id'], 'departments_id_pg_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('departments');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
