<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - enabled_yape: boolean
 * - qrcode_yape: varchar(255)
 * - name_yape: varchar(255)
 * - telephone_yape: varchar(255)
 * - enabled_mp: boolean
 * - access_token_mp: varchar(255)
 * - public_key_mp: varchar(255)
 * - publickey_culqi: text
 * - privatekey_culqi: text
 * - enabled_culqi: boolean
 * - enabled_izipay: boolean
 * - default_payment_for_payment_links: char(2)
 * - idrsa_culqi: text
 * - rsa_culqi: text
 * - username_izipay: varchar(255)
 * - password_izipay: varchar(255)
 * - publickey_izipay: varchar(255)
 * - sha256key_izipay: text
 * - created_at: timestamp
 * - updated_at: timestamp
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_configurations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->boolean('enabled_yape');
            $table->string('qrcode_yape', 255)->collation('pro9_ci_ai')->nullable();
            $table->string('name_yape', 255)->collation('pro9_ci_ai')->nullable();
            $table->string('telephone_yape', 255)->collation('pro9_ci_ai')->nullable();
            $table->boolean('enabled_mp')->default(false);
            $table->string('access_token_mp', 255)->collation('C')->nullable();
            $table->string('public_key_mp', 255)->collation('pro9_ci_ai')->nullable();
            $table->text('publickey_culqi')->collation('pro9_ci_ai')->nullable();
            $table->text('privatekey_culqi')->collation('pro9_ci_ai')->nullable();
            $table->boolean('enabled_culqi')->default(false);
            $table->boolean('enabled_izipay')->default(false);
            $table->char('default_payment_for_payment_links', 2)->collation('pro9_ci_ai')->nullable();
            $table->text('idrsa_culqi')->collation('pro9_ci_ai')->nullable();
            $table->text('rsa_culqi')->collation('pro9_ci_ai')->nullable();
            $table->string('username_izipay', 255)->collation('pro9_ci_ai')->nullable();
            $table->string('password_izipay', 255)->collation('C')->nullable();
            $table->string('publickey_izipay', 255)->collation('pro9_ci_ai')->nullable();
            $table->text('sha256key_izipay')->collation('pro9_ci_ai')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_configurations');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
