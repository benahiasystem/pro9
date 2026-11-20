<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: varchar(255)
 * - active: boolean
 * - description: varchar(255)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cat_note_credit_types', function (Blueprint $table) {
            $table->string('id', 255)->collation('sab_ci_ai');
            $table->boolean('active');
            $table->string('description', 255)->collation('sab_ci_ai');
            $table->index(['id'], 'cat_note_credit_types_id_index');
            $table->unique(['id'], 'cat_note_credit_types_id_pg_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cat_note_credit_types');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
