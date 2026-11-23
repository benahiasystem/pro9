<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - fiscal_profile: json
 * - id: bigint (secuencia)
 * - type: varchar(255)
 * - identity_document_type_id: varchar(255)
 * - external_identity_type: varchar(4)
 * - is_third_party: boolean
 * - third_party_rif: varchar(10)
 * - third_party_activity: varchar(100)
 * - number: varchar(255)
 * - name: varchar(255)
 * - trade_name: varchar(255)
 * - internal_code: varchar(100)
 * - barcode: varchar(150)
 * - country_id: char(2)
 * - nationality_id: char(2)
 * - department_id: char(2)
 * - province_id: char(4)
 * - district_id: char(6)
 * - address_type_id: varchar(255)
 * - address: varchar(255)
 * - establishment_code: varchar(4)
 * - condition: varchar(255)
 * - state: varchar(255)
 * - email: varchar(255)
 * - password: varchar(255)
 * - remember_token: varchar(255)
 * - telephone: varchar(255)
 * - accumulated_points: numeric(30,12)
 * - is_agent_retention: boolean
 * - person_type_id: bigint
 * - contact: json
 * - comment: varchar(255)
 * - enabled: boolean
 * - website: text
 * - zone: text
 * - observation: text
 * - created_at: timestamp
 * - updated_at: timestamp
 * - status: smallint
 * - credit_days: bigint
 * - optional_email: text
 * - parent_id: bigint
 * - zone_id: bigint
 * - seller_id: bigint
 * - has_discount: boolean
 * - discount_type: char(2)
 * - discount_amount: numeric(30,12)
 * - text_filter: text
 * - calculation_snapshot: json
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('persons', function (Blueprint $table) {
            $table->json('fiscal_profile')->nullable();
            $table->bigIncrements('id');
            $table->string('type', 255)->collation('sab_ci_ai');
            $table->string('identity_document_type_id', 255)->collation('sab_ci_ai');
            $table->string('external_identity_type', 4)->collation('C')->nullable();
            $table->boolean('is_third_party')->default(false);
            $table->string('third_party_rif', 10)->collation('sab_ci_ai')->nullable();
            $table->string('third_party_activity', 100)->collation('sab_ci_ai')->nullable();
            $table->string('number', 255)->collation('sab_ci_ai');
            $table->string('name', 255)->collation('sab_ci_ai');
            $table->string('trade_name', 255)->collation('sab_ci_ai')->nullable();
            $table->string('internal_code', 100)->collation('sab_ci_ai')->nullable();
            $table->string('barcode', 150)->collation('sab_ci_ai')->nullable();
            $table->char('country_id', 2)->collation('sab_ci_ai');
            $table->char('nationality_id', 2)->collation('sab_ci_ai')->nullable();
            $table->char('department_id', 2)->collation('sab_ci_ai')->nullable();
            $table->char('province_id', 4)->collation('sab_ci_ai')->nullable();
            $table->char('district_id', 6)->collation('sab_ci_ai')->nullable();
            $table->string('address_type_id', 255)->collation('sab_ci_ai')->nullable();
            $table->string('address', 255)->collation('sab_ci_ai')->nullable();
            $table->string('establishment_code', 4)->collation('sab_ci_ai')->default('0000');
            $table->string('condition', 255)->collation('sab_ci_ai')->nullable();
            $table->string('state', 255)->collation('sab_ci_ai')->nullable();
            $table->string('email', 255)->collation('sab_ci_ai')->nullable();
            $table->string('password', 255)->collation('C')->nullable();
            $table->string('remember_token', 255)->collation('C')->nullable();
            $table->string('telephone', 255)->collation('sab_ci_ai')->nullable();
            $table->decimal('accumulated_points', 30, 12)->default('0.00')->comment('sistema por puntos');
            $table->boolean('is_agent_retention')->default(false);
            $table->bigInteger('person_type_id')->nullable();
            $table->json('contact')->nullable();
            $table->string('comment', 255)->collation('sab_ci_ai')->nullable();
            $table->boolean('enabled')->default(true);
            $table->text('website')->collation('sab_ci_ai')->nullable()->comment('Sitio Web');
            $table->text('zone')->collation('sab_ci_ai')->nullable()->comment('Zona');
            $table->text('observation')->collation('sab_ci_ai')->nullable()->comment('Observaciones');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->smallInteger('status')->default('1');
            $table->bigInteger('credit_days')->nullable()->default('0')->comment('establece los dias de credito');
            $table->text('optional_email')->collation('sab_ci_ai')->nullable()->comment('Conjunto de correos de envio');
            $table->bigInteger('parent_id')->nullable()->default('0')->comment('Se relaciona con si mismo, numero mayor a 0 es el id del padre.');
            $table->bigInteger('zone_id')->nullable();
            $table->bigInteger('seller_id')->nullable();
            $table->boolean('has_discount')->default(false);
            $table->char('discount_type', 2)->collation('sab_ci_ai')->default('01');
            $table->decimal('discount_amount', 30, 12)->default('0.00');
            $table->text('text_filter')->collation('sab_ci_ai')->nullable();
            $table->json('calculation_snapshot')->nullable()->comment('Exact decimal fractions v1');
            $table->unique(['type', 'identity_document_type_id', 'country_id', 'external_identity_type', 'number'], 'persons_external_identity_unique');
            $table->unique(['third_party_rif'], 'persons_third_party_rif_unique');
            $table->index(['type', 'is_third_party', 'enabled'], 'persons_third_party_enabled_index');
            $table->index(['identity_document_type_id'], 'persons_identity_document_type_id_foreign');
            $table->index(['country_id'], 'persons_country_id_foreign');
            $table->index(['department_id'], 'persons_department_id_foreign');
            $table->index(['province_id'], 'persons_province_id_foreign');
            $table->index(['district_id'], 'persons_district_id_foreign');
            $table->index(['name'], 'persons_name_index');
            $table->index(['number'], 'persons_number_index');
            $table->index(['type'], 'persons_type_index');
            $table->index(['person_type_id'], 'persons_person_type_id_foreign');
            $table->index(['enabled'], 'persons_enabled_index');
            $table->index(['address_type_id'], 'persons_address_type_id_foreign');
            $table->index(['seller_id'], 'persons_seller_id_index');
            $table->index(['nationality_id'], 'persons_nationality_id_foreign');
        });
        DB::statement('ALTER TABLE "persons" ADD CONSTRAINT "persons_type_check" CHECK ("type" IN (\'customers\',\'suppliers\'))');
        DB::statement('ALTER TABLE "persons" ADD CONSTRAINT "persons_status_range" CHECK ("status" BETWEEN -128 AND 127)');
        DB::statement('ALTER TABLE "persons" ADD CONSTRAINT "persons_credit_days_range" CHECK ("credit_days" BETWEEN 0 AND 4294967295)');
        DB::statement('ALTER TABLE "persons" ADD CONSTRAINT "persons_parent_id_range" CHECK ("parent_id" >= 0)');
        DB::statement('ALTER TABLE "persons" ADD CONSTRAINT "persons_zone_id_range" CHECK ("zone_id" >= 0)');
        DB::statement('ALTER TABLE "persons" ADD CONSTRAINT "persons_seller_id_range" CHECK ("seller_id" >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('persons');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
