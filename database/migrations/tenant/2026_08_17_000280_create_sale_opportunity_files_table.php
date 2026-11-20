<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - sale_opportunity_id: bigint
 * - filename: varchar(255)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_opportunity_files', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('sale_opportunity_id');
            $table->string('filename', 255)->collation('sab_ci_ai');
            $table->index(['sale_opportunity_id'], 'sale_opportunity_files_sale_opportunity_id_foreign');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_opportunity_files');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
