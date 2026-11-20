<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - formats: varchar(255)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('preprinted_format_templates', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('formats', 255)->collation('pro9_ci_ai');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('preprinted_format_templates');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
