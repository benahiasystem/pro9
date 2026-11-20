<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - document_id: bigint
 * - seat_number: varchar(255)
 * - passenger_manifest: varchar(255)
 * - identity_document_type_id: varchar(255)
 * - number_identity_document: varchar(255)
 * - passenger_fullname: varchar(255)
 * - origin_district_id: json
 * - origin_address: varchar(255)
 * - destinatation_district_id: json
 * - destinatation_address: varchar(255)
 * - start_date: date
 * - start_time: time
 * - created_at: timestamp
 * - updated_at: timestamp
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_transports', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('document_id');
            $table->string('seat_number', 255)->collation('pro9_ci_ai');
            $table->string('passenger_manifest', 255)->collation('pro9_ci_ai');
            $table->string('identity_document_type_id', 255)->collation('pro9_ci_ai');
            $table->string('number_identity_document', 255)->collation('pro9_ci_ai');
            $table->string('passenger_fullname', 255)->collation('pro9_ci_ai');
            $table->json('origin_district_id');
            $table->string('origin_address', 255)->collation('pro9_ci_ai')->nullable();
            $table->json('destinatation_district_id');
            $table->string('destinatation_address', 255)->collation('pro9_ci_ai')->nullable();
            $table->date('start_date')->nullable();
            $table->time('start_time')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index(['document_id'], 'document_transports_document_id_foreign');
            $table->index(['identity_document_type_id'], 'document_transports_identity_document_type_id_foreign');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_transports');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
