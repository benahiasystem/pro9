<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - user_id: bigint
 * - external_id: char(36)
 * - establishment_id: bigint
 * - establishment: json
 * - fiscal_environment: varchar(16)
 * - state_type_id: char(2)
 * - ubl_version: varchar(255)
 * - document_type_id: varchar(255)
 * - series: varchar(20)
 * - number: integer
 * - date_of_issue: date
 * - time_of_issue: time
 * - supplier_id: bigint
 * - supplier: json
 * - retention_type_id: char(2)
 * - observations: text
 * - currency_type_id: varchar(255)
 * - total_retention: numeric(30,12)
 * - total: numeric(30,12)
 * - legends: json
 * - optional: json
 * - filename: varchar(255)
 * - has_pdf: boolean
 * - created_at: timestamp
 * - updated_at: timestamp
 * - calculation_snapshot: json
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('retentions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('user_id');
            $table->char('external_id', 36)->collation('sab_ci_ai');
            $table->bigInteger('establishment_id');
            $table->json('establishment');
            $table->string('fiscal_environment', 16)->collation('sab_ci_ai');
            $table->char('state_type_id', 2)->collation('sab_ci_ai');
            $table->string('ubl_version', 255)->collation('sab_ci_ai');
            $table->string('document_type_id', 255)->collation('sab_ci_ai');
            $table->string('series', 20)->collation('sab_ci_ai');
            $table->integer('number');
            $table->date('date_of_issue');
            $table->time('time_of_issue');
            $table->bigInteger('supplier_id');
            $table->json('supplier');
            $table->char('retention_type_id', 2)->collation('sab_ci_ai');
            $table->text('observations')->collation('sab_ci_ai')->nullable();
            $table->string('currency_type_id', 255)->collation('sab_ci_ai');
            $table->decimal('total_retention', 30, 12);
            $table->decimal('total', 30, 12);
            $table->json('legends')->nullable();
            $table->json('optional')->nullable();
            $table->string('filename', 255)->collation('sab_ci_ai')->nullable();
            $table->boolean('has_pdf')->default(false);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->json('calculation_snapshot')->nullable()->comment('Exact decimal fractions v1');
            $table->index(['user_id'], 'retentions_user_id_foreign');
            $table->index(['establishment_id'], 'retentions_establishment_id_foreign');
            $table->index(['fiscal_environment'], 'retentions_fiscal_environment_foreign');
            $table->index(['state_type_id'], 'retentions_state_type_id_foreign');
            $table->index(['document_type_id'], 'retentions_document_type_id_foreign');
            $table->index(['supplier_id'], 'retentions_supplier_id_foreign');
            $table->index(['retention_type_id'], 'retentions_retention_type_id_foreign');
            $table->index(['currency_type_id'], 'retentions_currency_type_id_foreign');
        });
        DB::statement('ALTER TABLE "retentions" ADD CONSTRAINT "retentions_number_range" CHECK ("number" BETWEEN -2147483648 AND 2147483647)');
    }

    public function down(): void
    {
        Schema::dropIfExists('retentions');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
