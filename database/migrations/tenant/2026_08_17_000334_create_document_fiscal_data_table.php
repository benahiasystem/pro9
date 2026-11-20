<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - document_id: bigint
 * - provider_type_id: smallint
 * - transaction_type_id: varchar(2)
 * - special_tax_regime_id: smallint
 * - catalog_snapshot: json
 * - third_party: json
 * - third_party_person_id: bigint
 * - conditional_data: json
 * - iva_retention_prevision: json
 * - islr_retention_prevision: json
 * - created_at: timestamp
 * - updated_at: timestamp
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_fiscal_data', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('document_id');
            $table->smallInteger('provider_type_id')->nullable();
            $table->string('transaction_type_id', 2)->collation('sab_ci_ai')->default('01');
            $table->smallInteger('special_tax_regime_id')->nullable();
            $table->json('catalog_snapshot');
            $table->json('third_party')->nullable();
            $table->bigInteger('third_party_person_id')->nullable();
            $table->json('conditional_data')->nullable();
            $table->json('iva_retention_prevision')->nullable();
            $table->json('islr_retention_prevision')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['document_id'], 'document_fiscal_data_document_unique');
            $table->index(['third_party_person_id'], 'document_fiscal_data_third_party_person_id_foreign');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_fiscal_data');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
