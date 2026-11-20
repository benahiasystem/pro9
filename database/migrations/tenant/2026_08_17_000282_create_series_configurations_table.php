<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - series_id: bigint
 * - document_type_id: varchar(255)
 * - series: varchar(255)
 * - number: varchar(255)
 * - created_at: timestamp
 * - updated_at: timestamp
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('series_configurations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('series_id');
            $table->string('document_type_id', 255)->collation('sab_ci_ai');
            $table->string('series', 255)->collation('sab_ci_ai');
            $table->string('number', 255)->collation('sab_ci_ai');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index(['series_id'], 'series_configurations_series_id_foreign');
            $table->index(['series'], 'series_configurations_series_index');
            $table->index(['number'], 'series_configurations_number_index');
            $table->index(['document_type_id'], 'series_configurations_document_type_id_foreign');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('series_configurations');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
