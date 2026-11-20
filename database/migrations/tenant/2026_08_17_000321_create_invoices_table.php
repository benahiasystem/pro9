<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - document_id: bigint
 * - operation_type_id: varchar(255)
 * - date_of_due: date
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('document_id');
            $table->string('operation_type_id', 255)->collation('pro9_ci_ai');
            $table->date('date_of_due');
            $table->index(['document_id'], 'invoices_document_id_foreign');
            $table->index(['operation_type_id'], 'invoices_operation_type_id_foreign');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
