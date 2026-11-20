<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - person_id: bigint
 * - department_id: char(2)
 * - province_id: char(4)
 * - district_id: char(6)
 * - address: varchar(255)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('person_address', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('person_id');
            $table->char('department_id', 2)->collation('pro9_ci_ai')->nullable();
            $table->char('province_id', 4)->collation('pro9_ci_ai')->nullable();
            $table->char('district_id', 6)->collation('pro9_ci_ai')->nullable();
            $table->string('address', 255)->collation('pro9_ci_ai')->nullable();
            $table->index(['person_id'], 'person_address_person_id_foreign');
            $table->index(['department_id'], 'person_address_department_id_foreign');
            $table->index(['province_id'], 'person_address_province_id_foreign');
            $table->index(['district_id'], 'person_address_district_id_foreign');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('person_address');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
