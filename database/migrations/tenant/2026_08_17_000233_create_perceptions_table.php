<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - user_id: bigint
 * - establishment_id: bigint
 * - establishment: json
 * - external_id: char(36)
 * - fiscal_environment: varchar(16)
 * - state_type_id: char(2)
 * - ubl_version: varchar(255)
 * - document_type_id: varchar(255)
 * - series: varchar(20)
 * - number: integer
 * - date_of_issue: date
 * - customer_id: bigint
 * - customer: json
 * - currency_type_id: varchar(255)
 * - perception_type_id: varchar(255)
 * - observations: text
 * - total_perception: numeric(30,12)
 * - total: numeric(30,12)
 * - optional: json
 * - legends: json
 * - filename: varchar(255)
 * - time_of_issue: time
 * - has_pdf: boolean
 * - created_at: timestamp
 * - updated_at: timestamp
 * - calculation_snapshot: json
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('perceptions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('user_id');
            $table->bigInteger('establishment_id');
            $table->json('establishment');
            $table->char('external_id', 36)->collation('pro9_ci_ai');
            $table->string('fiscal_environment', 16)->collation('pro9_ci_ai');
            $table->char('state_type_id', 2)->collation('pro9_ci_ai');
            $table->string('ubl_version', 255)->collation('pro9_ci_ai');
            $table->string('document_type_id', 255)->collation('pro9_ci_ai');
            $table->string('series', 20)->collation('pro9_ci_ai');
            $table->integer('number');
            $table->date('date_of_issue');
            $table->bigInteger('customer_id');
            $table->json('customer');
            $table->string('currency_type_id', 255)->collation('pro9_ci_ai');
            $table->string('perception_type_id', 255)->collation('pro9_ci_ai');
            $table->text('observations')->collation('pro9_ci_ai')->nullable();
            $table->decimal('total_perception', 30, 12);
            $table->decimal('total', 30, 12);
            $table->json('optional')->nullable();
            $table->json('legends')->nullable();
            $table->string('filename', 255)->collation('pro9_ci_ai')->nullable();
            $table->time('time_of_issue');
            $table->boolean('has_pdf')->default(false);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->json('calculation_snapshot')->nullable()->comment('Exact decimal fractions v1');
            $table->index(['user_id'], 'perceptions_user_id_foreign');
            $table->index(['establishment_id'], 'perceptions_establishment_id_foreign');
            $table->index(['fiscal_environment'], 'perceptions_fiscal_environment_foreign');
            $table->index(['state_type_id'], 'perceptions_state_type_id_foreign');
            $table->index(['document_type_id'], 'perceptions_document_type_id_foreign');
            $table->index(['currency_type_id'], 'perceptions_currency_type_id_foreign');
            $table->index(['perception_type_id'], 'perceptions_perception_type_id_foreign');
            $table->index(['customer_id'], 'perceptions_customer_id_foreign');
            $table->index(['number'], 'perceptions_number_index');
            $table->index(['series'], 'perceptions_series_index');
        });
        DB::statement('ALTER TABLE "perceptions" ADD CONSTRAINT "perceptions_number_range" CHECK ("number" BETWEEN -2147483648 AND 2147483647)');
    }

    public function down(): void
    {
        Schema::dropIfExists('perceptions');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
