<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: char(6)
 * - province_id: char(4)
 * - description: varchar(255)
 * - active: boolean
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('districts', function (Blueprint $table) {
            $table->char('id', 6)->collation('sab_ci_ai');
            $table->char('province_id', 4)->collation('sab_ci_ai');
            $table->string('description', 255)->collation('sab_ci_ai');
            $table->boolean('active')->default(true);
            $table->index(['province_id'], 'districts_province_id_foreign');
            $table->index(['id'], 'districts_id_index');
            $table->unique(['id'], 'districts_id_pg_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('districts');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
