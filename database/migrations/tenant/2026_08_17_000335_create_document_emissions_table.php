<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - document_id: bigint
 * - provider: varchar(32)
 * - fiscal_environment: varchar(16)
 * - contract_version: varchar(100)
 * - operation_key: char(36)
 * - status: varchar(255)
 * - payload: json
 * - response: json
 * - control_number: varchar(32)
 * - authorization: varchar(255)
 * - assigned_at: timestamp
 * - control_assigned_at: timestamp
 * - file_path: varchar(255)
 * - consulta_url: varchar(255)
 * - created_at: timestamp
 * - updated_at: timestamp
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_emissions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('document_id');
            $table->string('provider', 32)->collation('pro9_ci_ai')->default('HKA');
            $table->string('fiscal_environment', 16)->collation('pro9_ci_ai');
            $table->string('contract_version', 100)->collation('pro9_ci_ai')->nullable();
            $table->char('operation_key', 36)->collation('pro9_ci_ai');
            $table->string('status', 255)->collation('pro9_ci_ai')->default('not_requested');
            $table->json('payload')->nullable();
            $table->json('response')->nullable();
            $table->string('control_number', 32)->collation('pro9_ci_ai')->nullable();
            $table->string('authorization', 255)->collation('pro9_ci_ai')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('control_assigned_at')->nullable();
            $table->string('file_path', 255)->collation('pro9_ci_ai')->nullable();
            $table->string('consulta_url', 255)->collation('pro9_ci_ai')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['document_id'], 'document_emissions_document_unique');
            $table->unique(['operation_key'], 'document_emissions_operation_unique');
        });
        DB::statement('ALTER TABLE "document_emissions" ADD CONSTRAINT "document_emissions_status_check" CHECK ("status" IN (\'not_requested\',\'prepared\',\'pending\',\'confirmed\',\'rejected\',\'uncertain\',\'cancelled\'))');
    }

    public function down(): void
    {
        Schema::dropIfExists('document_emissions');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
