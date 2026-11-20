<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - establishment_id: bigint
 * - document_type_id: varchar(255)
 * - number: varchar(255)
 * - contingency: boolean
 * - dedicated: boolean
 * - series_device_group_id: bigint
 * - in_use: boolean
 * - created_at: timestamp
 * - updated_at: timestamp
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('series', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('establishment_id');
            $table->string('document_type_id', 255)->collation('pro9_ci_ai');
            $table->string('number', 255)->collation('pro9_ci_ai')->default('');
            $table->boolean('contingency')->default(false);
            $table->boolean('dedicated')->default(false);
            $table->bigInteger('series_device_group_id')->nullable();
            $table->boolean('in_use')->default(false)->comment('True al emitir el primer comprobante en la serie');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['establishment_id', 'document_type_id', 'number'], 'series_document_type_number_unique');
            $table->index(['establishment_id'], 'series_establishment_id_foreign');
            $table->index(['document_type_id'], 'series_document_type_id_foreign');
            $table->index(['series_device_group_id'], 'series_series_device_group_id_foreign');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('series');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
