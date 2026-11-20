<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - perception_id: bigint
 * - document_type_id: varchar(255)
 * - number: varchar(255)
 * - date_of_issue: date
 * - date_of_perception: date
 * - currency_type_id: varchar(255)
 * - total_document: numeric(30,12)
 * - total_perception: numeric(30,12)
 * - payments: json
 * - series: varchar(255)
 * - exchange_rate: json
 * - total_to_pay: numeric(30,12)
 * - total_payment: numeric(30,12)
 * - calculation_snapshot: json
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('perception_documents', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('perception_id');
            $table->string('document_type_id', 255)->collation('sab_ci_ai');
            $table->string('number', 255)->collation('sab_ci_ai');
            $table->date('date_of_issue');
            $table->date('date_of_perception');
            $table->string('currency_type_id', 255)->collation('sab_ci_ai');
            $table->decimal('total_document', 30, 12);
            $table->decimal('total_perception', 30, 12);
            $table->json('payments');
            $table->string('series', 255)->collation('sab_ci_ai');
            $table->json('exchange_rate');
            $table->decimal('total_to_pay', 30, 12);
            $table->decimal('total_payment', 30, 12);
            $table->json('calculation_snapshot')->nullable()->comment('Exact decimal fractions v1');
            $table->index(['perception_id'], 'perception_details_perception_id_foreign');
            $table->index(['document_type_id'], 'perception_documents_document_type_id_foreign');
            $table->index(['currency_type_id'], 'perception_documents_currency_type_id_foreign');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('perception_documents');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
