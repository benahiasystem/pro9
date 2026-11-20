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
        Schema::create('countries', function (Blueprint $table) {
            $table->char('id', 2)->collation('sab_ci_ai');
            $table->string('description', 255)->collation('sab_ci_ai');
            $table->boolean('active')->default(true);
            $table->index(['id'], 'countries_id_index');
            $table->unique(['id'], 'countries_id_pg_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('countries');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
