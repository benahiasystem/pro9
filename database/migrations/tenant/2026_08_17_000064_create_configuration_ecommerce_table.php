<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - information_contact_name: varchar(255)
 * - information_contact_email: varchar(255)
 * - information_contact_phone: varchar(255)
 * - information_contact_address: varchar(255)
 * - phone_whatsapp: text
 * - script_paypal: text
 * - logo: varchar(255)
 * - link_youtube: text
 * - link_twitter: text
 * - link_tiktok: text
 * - link_instagram: text
 * - color_ecommerce: varchar(255)
 * - link_facebook: text
 * - tag_support: text
 * - tag_dollar: text
 * - tag_shipping: text
 * - token_public_culqui: text
 * - token_private_culqui: text
 * - title_one_customised_link: text
 * - title_two_customised_link: text
 * - title_three_customised_link: text
 * - customised_link_one: text
 * - customised_link_two: text
 * - customised_link_three: text
 * - preferences: json
 * - publicidad_activa: boolean
 * - publicidad_texto: varchar(255)
 * - publicidad_color_fondo: varchar(255)
 * - publicidad_link: varchar(255)
 * - terms_conditions: text
 * - privacy_policy: text
 * - about_us: text
 * - delivery_no_coverage_message: text
 * - enable_electronic_documents: boolean
 * - enable_store_pickup: boolean
 * - quotation_enabled: boolean
 * - quotation_mode: varchar(30)
 * - quotation_show_prices: boolean
 * - quotation_success_message: text
 * - quotation_validity_days: smallint
 * - quotation_terms: text
 * - enable_yape: boolean
 * - enable_transfer: boolean
 * - created_at: timestamp
 * - updated_at: timestamp
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configuration_ecommerce', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('information_contact_name', 255)->collation('sab_ci_ai');
            $table->string('information_contact_email', 255)->collation('sab_ci_ai');
            $table->string('information_contact_phone', 255)->collation('sab_ci_ai');
            $table->string('information_contact_address', 255)->collation('sab_ci_ai')->nullable();
            $table->text('phone_whatsapp')->collation('sab_ci_ai')->nullable();
            $table->text('script_paypal')->collation('sab_ci_ai')->nullable();
            $table->string('logo', 255)->collation('sab_ci_ai')->nullable();
            $table->text('link_youtube')->collation('sab_ci_ai')->nullable();
            $table->text('link_twitter')->collation('sab_ci_ai')->nullable();
            $table->text('link_tiktok')->collation('sab_ci_ai')->nullable();
            $table->text('link_instagram')->collation('sab_ci_ai')->nullable();
            $table->string('color_ecommerce', 255)->collation('sab_ci_ai')->nullable();
            $table->text('link_facebook')->collation('sab_ci_ai')->nullable();
            $table->text('tag_support')->collation('sab_ci_ai')->nullable();
            $table->text('tag_dollar')->collation('sab_ci_ai')->nullable();
            $table->text('tag_shipping')->collation('sab_ci_ai')->nullable();
            $table->text('token_public_culqui')->collation('C')->nullable();
            $table->text('token_private_culqui')->collation('C')->nullable();
            $table->text('title_one_customised_link')->collation('sab_ci_ai')->nullable();
            $table->text('title_two_customised_link')->collation('sab_ci_ai')->nullable();
            $table->text('title_three_customised_link')->collation('sab_ci_ai')->nullable();
            $table->text('customised_link_one')->collation('sab_ci_ai')->nullable();
            $table->text('customised_link_two')->collation('sab_ci_ai')->nullable();
            $table->text('customised_link_three')->collation('sab_ci_ai')->nullable();
            $table->json('preferences')->nullable();
            $table->boolean('publicidad_activa')->default(false);
            $table->string('publicidad_texto', 255)->collation('sab_ci_ai')->nullable();
            $table->string('publicidad_color_fondo', 255)->collation('sab_ci_ai')->default('#000000');
            $table->string('publicidad_link', 255)->collation('sab_ci_ai')->nullable();
            $table->text('terms_conditions')->collation('sab_ci_ai')->nullable();
            $table->text('privacy_policy')->collation('sab_ci_ai')->nullable();
            $table->text('about_us')->collation('sab_ci_ai')->nullable();
            $table->text('delivery_no_coverage_message')->collation('sab_ci_ai')->nullable();
            $table->boolean('enable_electronic_documents')->default(false);
            $table->boolean('enable_store_pickup')->default(false);
            $table->boolean('quotation_enabled')->default(false);
            $table->string('quotation_mode', 30)->collation('sab_ci_ai')->default('quote_and_sell');
            $table->boolean('quotation_show_prices')->default(true);
            $table->text('quotation_success_message')->collation('sab_ci_ai')->nullable();
            $table->smallInteger('quotation_validity_days')->default('7');
            $table->text('quotation_terms')->collation('sab_ci_ai')->nullable();
            $table->boolean('enable_yape')->default(false);
            $table->boolean('enable_transfer')->default(false);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
        DB::statement('ALTER TABLE "configuration_ecommerce" ADD CONSTRAINT "configuration_ecommerce_quotation_validity_days_range" CHECK ("quotation_validity_days" BETWEEN 0 AND 255)');
    }

    public function down(): void
    {
        Schema::dropIfExists('configuration_ecommerce');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
