<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - person_id: bigint
 * - country_id: char(2)
 * - department_id: char(2)
 * - province_id: char(4)
 * - district_id: char(6)
 * - address: varchar(255)
 * - phone: varchar(255)
 * - email: varchar(255)
 * - main: boolean
 * - establishment_code: varchar(4)
 * - has_consigned: boolean
 * - consigned_id: bigint
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('person_addresses', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('person_id');
            $table->char('country_id', 2)->collation('pro9_ci_ai');
            $table->char('department_id', 2)->collation('pro9_ci_ai')->nullable();
            $table->char('province_id', 4)->collation('pro9_ci_ai')->nullable();
            $table->char('district_id', 6)->collation('pro9_ci_ai')->nullable();
            $table->string('address', 255)->collation('pro9_ci_ai')->nullable();
            $table->string('phone', 255)->collation('pro9_ci_ai')->nullable();
            $table->string('email', 255)->collation('pro9_ci_ai')->nullable();
            $table->boolean('main')->default(false);
            $table->string('establishment_code', 4)->collation('pro9_ci_ai')->default('0000');
            $table->boolean('has_consigned')->default(false);
            $table->bigInteger('consigned_id')->nullable();
            $table->index(['person_id'], 'person_addresses_person_id_foreign');
            $table->index(['country_id'], 'person_addresses_country_id_foreign');
            $table->index(['department_id'], 'person_addresses_department_id_foreign');
            $table->index(['province_id'], 'person_addresses_province_id_foreign');
            $table->index(['district_id'], 'person_addresses_district_id_foreign');
        });
        DB::statement('ALTER TABLE "person_addresses" ADD CONSTRAINT "person_addresses_consigned_id_range" CHECK ("consigned_id" >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('person_addresses');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
