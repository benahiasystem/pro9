<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - formats: varchar(255)
 * - urls: json
 * - is_custom_ticket: boolean
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('format_templates', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('formats', 255)->collation('sab_ci_ai');
            $table->json('urls')->nullable();
            $table->boolean('is_custom_ticket')->default(false);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('format_templates');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
