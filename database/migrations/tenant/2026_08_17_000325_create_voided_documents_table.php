<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - voided_id: bigint
 * - document_id: bigint
 * - description: varchar(255)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voided_documents', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('voided_id');
            $table->bigInteger('document_id');
            $table->string('description', 255)->collation('sab_ci_ai');
            $table->index(['voided_id'], 'voided_documents_voided_id_foreign');
            $table->index(['document_id'], 'voided_documents_document_id_foreign');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voided_documents');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
