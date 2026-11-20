<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - name: varchar(255)
 * - email: varchar(255)
 * - email_verified_at: timestamp
 * - password: varchar(255)
 * - api_token: varchar(255)
 * - establishment_id: bigint
 * - type: varchar(255)
 * - active: boolean
 * - is_multi_user: boolean
 * - multi_user_id: bigint
 * - permission_edit_cpe: boolean
 * - permission_edit_item_prices: boolean
 * - create_payment: boolean
 * - delete_purchase: boolean
 * - annular_purchase: boolean
 * - edit_purchase: boolean
 * - delete_payment: boolean
 * - secret_login_time: timestamp
 * - recreate_documents: boolean
 * - locked: boolean
 * - bot_enabled: boolean
 * - from_guest_register: boolean
 * - pinned_items: json
 * - menu_order: json
 * - show_only_active_menu: boolean
 * - multiple_default_document_types: boolean
 * - photo_filename: varchar(255)
 * - position: varchar(255)
 * - contract_date: date
 * - date_of_birth: date
 * - corporate_cell_phone: varchar(255)
 * - personal_cell_phone: varchar(255)
 * - corporate_email: varchar(255)
 * - personal_email: varchar(255)
 * - last_names: varchar(255)
 * - names: varchar(255)
 * - identity_document_type_id: varchar(255)
 * - number: varchar(255)
 * - address: varchar(255)
 * - telephone: varchar(255)
 * - remember_token: varchar(100)
 * - last_password_update: timestamp
 * - restaurant_role_id: bigint
 * - restaurant_pin: varchar(4)
 * - created_at: timestamp
 * - updated_at: timestamp
 * - document_id: char(255)
 * - series_id: bigint
 * - zone_id: bigint
 * - permission_edit_sale_note: boolean
 * - change_seller: boolean
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 255)->collation('sab_ci_ai');
            $table->string('email', 255)->collation('sab_ci_ai');
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password', 255)->collation('C');
            $table->string('api_token', 255)->collation('C')->nullable();
            $table->bigInteger('establishment_id')->nullable();
            $table->string('type', 255)->collation('sab_ci_ai');
            $table->boolean('active')->default(true);
            $table->boolean('is_multi_user')->default(false);
            $table->bigInteger('multi_user_id')->nullable();
            $table->boolean('permission_edit_cpe')->default(false);
            $table->boolean('permission_edit_item_prices')->default(true);
            $table->boolean('create_payment')->default(true);
            $table->boolean('delete_purchase')->default(true);
            $table->boolean('annular_purchase')->default(true);
            $table->boolean('edit_purchase')->default(true);
            $table->boolean('delete_payment')->default(true);
            $table->timestamp('secret_login_time')->nullable();
            $table->boolean('recreate_documents')->default(false);
            $table->boolean('locked')->default(false);
            $table->boolean('bot_enabled')->default(false);
            $table->boolean('from_guest_register')->default(false);
            $table->json('pinned_items')->nullable();
            $table->json('menu_order')->nullable();
            $table->boolean('show_only_active_menu')->default(false);
            $table->boolean('multiple_default_document_types')->default(false);
            $table->string('photo_filename', 255)->collation('sab_ci_ai')->nullable();
            $table->string('position', 255)->collation('sab_ci_ai')->nullable();
            $table->date('contract_date')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('corporate_cell_phone', 255)->collation('sab_ci_ai')->nullable();
            $table->string('personal_cell_phone', 255)->collation('sab_ci_ai')->nullable();
            $table->string('corporate_email', 255)->collation('sab_ci_ai')->nullable();
            $table->string('personal_email', 255)->collation('sab_ci_ai')->nullable();
            $table->string('last_names', 255)->collation('sab_ci_ai')->nullable();
            $table->string('names', 255)->collation('sab_ci_ai')->nullable();
            $table->string('identity_document_type_id', 255)->collation('sab_ci_ai')->nullable();
            $table->string('number', 255)->collation('sab_ci_ai')->nullable();
            $table->string('address', 255)->collation('sab_ci_ai')->nullable();
            $table->string('telephone', 255)->collation('sab_ci_ai')->nullable();
            $table->string('remember_token', 100)->collation('C')->nullable();
            $table->timestamp('last_password_update')->nullable();
            $table->bigInteger('restaurant_role_id')->nullable();
            $table->string('restaurant_pin', 4)->collation('sab_ci_ai')->nullable()->default('1111');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->char('document_id', 255)->collation('sab_ci_ai')->nullable()->comment('Relacion con tipo de documentos');
            $table->bigInteger('series_id')->nullable()->comment('Relacion con series');
            $table->bigInteger('zone_id')->nullable();
            $table->boolean('permission_edit_sale_note')->default(false);
            $table->boolean('change_seller')->default(false);
            $table->unique(['email'], 'users_email_unique');
            $table->unique(['api_token'], 'users_api_token_unique');
            $table->index(['establishment_id'], 'users_establishment_id_foreign');
            $table->index(['restaurant_role_id'], 'users_restaurant_role_id_foreign');
            $table->index(['is_multi_user'], 'users_is_multi_user_index');
            $table->index(['active'], 'users_active_index');
        });
        DB::statement('ALTER TABLE "users" ADD CONSTRAINT "users_type_check" CHECK ("type" IN (\'admin\',\'seller\',\'integrator\',\'client\'))');
        DB::statement('ALTER TABLE "users" ADD CONSTRAINT "users_multi_user_id_range" CHECK ("multi_user_id" >= 0)');
        DB::statement('ALTER TABLE "users" ADD CONSTRAINT "users_series_id_range" CHECK ("series_id" >= 0)');
        DB::statement('ALTER TABLE "users" ADD CONSTRAINT "users_zone_id_range" CHECK ("zone_id" >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
