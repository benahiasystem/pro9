<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: char(4)
 * - department_id: char(2)
 * - description: varchar(255)
 * - active: boolean
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provinces', function (Blueprint $table) {
            $table->char('id', 4)->collation('pro9_ci_ai');
            $table->char('department_id', 2)->collation('pro9_ci_ai');
            $table->string('description', 255)->collation('pro9_ci_ai');
            $table->boolean('active')->default(true);
            $table->index(['department_id'], 'provinces_department_id_foreign');
            $table->index(['id'], 'provinces_id_index');
            $table->unique(['id'], 'provinces_id_pg_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provinces');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
