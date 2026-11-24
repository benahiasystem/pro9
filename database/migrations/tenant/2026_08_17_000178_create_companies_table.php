<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - igtf_enabled: boolean
 * - igtf_rate: numeric(30,12)
 * - fiscal_profile: json
 * - id: bigint (secuencia)
 * - identity_document_type_id: varchar(255)
 * - number: varchar(255)
 * - name: varchar(255)
 * - trade_name: varchar(255)
 * - fiscal_environment: varchar(16)
 * - fiscal_emission_mode: varchar(32)
 * - fiscal_configuration: text
 * - fiscal_credentials: text
 * - hka_authenticated_at: timestamp
 * - fiscal_environment_locked: boolean
 * - logo: varchar(255)
 * - logo_dark: varchar(255)
 * - app_logo: varchar(255)
 * - logo_store: varchar(255)
 * - favicon: varchar(150)
 * - claims_widget_custom_url: varchar(255)
 * - claims_widget_custom_url_active: boolean
 * - img_firm: varchar(255)
 * - integrated_query_client_id: varchar(255)
 * - integrated_query_client_secret: varchar(255)
 * - ws_api_phone_number_id: varchar(255)
 * - ws_api_token: text
 * - title_web: varchar(255)
 * - created_at: timestamp
 * - updated_at: timestamp
 * - cod_digemid: text
 * - digital_certificate_qztray: varchar(255)
 * - private_certificate_qztray: varchar(255)
 * - qr_api_enable_ws: boolean
 * - qr_api_url_ws: varchar(255)
 * - qr_api_key_ws: varchar(255)
 * - security_code: varchar(255)
 * - name_person_support_contaweb: varchar(255)
 * - telephone_support_contaweb: varchar(255)
 * - email_support_contaweb: varchar(255)
 * - calculation_snapshot: json
 * - rif_document: json
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->boolean('igtf_enabled')->default(false);
            $table->decimal('igtf_rate', 30, 12)->nullable();
            $table->json('fiscal_profile')->nullable();
            $table->bigIncrements('id');
            $table->string('identity_document_type_id', 255)->collation('sab_ci_ai');
            $table->string('number', 255)->collation('sab_ci_ai');
            $table->string('name', 255)->collation('sab_ci_ai');
            $table->string('trade_name', 255)->collation('sab_ci_ai')->nullable();
            $table->string('fiscal_environment', 16)->collation('sab_ci_ai');
            $table->string('fiscal_emission_mode', 32)->collation('sab_ci_ai');
            $table->text('fiscal_configuration')->collation('sab_ci_ai')->nullable();
            $table->text('fiscal_credentials')->collation('sab_ci_ai')->nullable();
            $table->timestamp('hka_authenticated_at')->nullable();
            $table->boolean('fiscal_environment_locked')->default(false);
            $table->string('logo', 255)->collation('sab_ci_ai')->nullable();
            $table->string('logo_dark', 255)->collation('sab_ci_ai')->nullable();
            $table->string('app_logo', 255)->collation('sab_ci_ai')->nullable();
            $table->string('logo_store', 255)->collation('sab_ci_ai')->nullable();
            $table->string('favicon', 150)->collation('sab_ci_ai')->nullable();
            $table->string('claims_widget_custom_url', 255)->collation('sab_ci_ai')->nullable();
            $table->boolean('claims_widget_custom_url_active')->default(false);
            $table->string('img_firm', 255)->collation('sab_ci_ai')->nullable();
            $table->string('integrated_query_client_id', 255)->collation('sab_ci_ai')->nullable();
            $table->string('integrated_query_client_secret', 255)->collation('C')->nullable();
            $table->string('ws_api_phone_number_id', 255)->collation('sab_ci_ai')->nullable();
            $table->text('ws_api_token')->collation('C')->nullable();
            $table->string('title_web', 255)->collation('sab_ci_ai')->default('Facturación Electrónica');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->text('cod_digemid')->collation('sab_ci_ai')->nullable()->comment('Codigo de establecimiento DIGEMID');
            $table->string('digital_certificate_qztray', 255)->collation('sab_ci_ai')->nullable();
            $table->string('private_certificate_qztray', 255)->collation('sab_ci_ai')->nullable();
            $table->boolean('qr_api_enable_ws')->default(false);
            $table->string('qr_api_url_ws', 255)->collation('sab_ci_ai')->nullable();
            $table->string('qr_api_key_ws', 255)->collation('C')->nullable();
            $table->string('security_code', 255)->collation('sab_ci_ai')->nullable();
            $table->string('name_person_support_contaweb', 255)->collation('sab_ci_ai')->nullable();
            $table->string('telephone_support_contaweb', 255)->collation('sab_ci_ai')->nullable();
            $table->string('email_support_contaweb', 255)->collation('sab_ci_ai')->nullable();
            $table->json('calculation_snapshot')->nullable()->comment('Exact decimal fractions v1');
            $table->json('rif_document')->nullable();
            $table->index(['identity_document_type_id'], 'companies_identity_document_type_id_foreign');
            $table->index(['fiscal_environment'], 'companies_fiscal_environment_foreign');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
