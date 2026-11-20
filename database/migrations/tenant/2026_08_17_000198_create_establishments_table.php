<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - description: varchar(255)
 * - country_id: char(2)
 * - department_id: char(2)
 * - province_id: char(4)
 * - district_id: char(6)
 * - address: varchar(255)
 * - email: varchar(255)
 * - telephone: varchar(255)
 * - code: varchar(255)
 * - aditional_information: varchar(255)
 * - web_address: varchar(255)
 * - trade_address: varchar(255)
 * - customer_id: bigint
 * - logo: varchar(150)
 * - template_pdf: varchar(255)
 * - has_igv_31556: boolean
 * - template_ticket_pdf: varchar(255)
 * - created_at: timestamp
 * - updated_at: timestamp
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('establishments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('description', 255)->collation('sab_ci_ai');
            $table->char('country_id', 2)->collation('sab_ci_ai');
            $table->char('department_id', 2)->collation('sab_ci_ai');
            $table->char('province_id', 4)->collation('sab_ci_ai');
            $table->char('district_id', 6)->collation('sab_ci_ai');
            $table->string('address', 255)->collation('sab_ci_ai');
            $table->string('email', 255)->collation('sab_ci_ai')->nullable();
            $table->string('telephone', 255)->collation('sab_ci_ai');
            $table->string('code', 255)->collation('sab_ci_ai');
            $table->string('aditional_information', 255)->collation('sab_ci_ai')->nullable();
            $table->string('web_address', 255)->collation('sab_ci_ai')->nullable();
            $table->string('trade_address', 255)->collation('sab_ci_ai')->nullable();
            $table->bigInteger('customer_id')->nullable();
            $table->string('logo', 150)->collation('sab_ci_ai')->nullable();
            $table->string('template_pdf', 255)->collation('sab_ci_ai')->default('default');
            $table->boolean('has_igv_31556')->default(false);
            $table->string('template_ticket_pdf', 255)->collation('sab_ci_ai')->default('default');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index(['country_id'], 'establishments_country_id_foreign');
            $table->index(['department_id'], 'establishments_department_id_foreign');
            $table->index(['province_id'], 'establishments_province_id_foreign');
            $table->index(['district_id'], 'establishments_district_id_foreign');
        });
        DB::statement('ALTER TABLE "establishments" ADD CONSTRAINT "establishments_customer_id_range" CHECK ("customer_id" >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('establishments');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
