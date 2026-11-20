<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - person_id: bigint
 * - address: varchar(255)
 * - location_id: json
 * - establishment_code: varchar(4)
 * - is_active: boolean
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dispatch_addresses', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('person_id');
            $table->string('address', 255)->collation('sab_ci_ai');
            $table->json('location_id');
            $table->string('establishment_code', 4)->collation('sab_ci_ai')->default('0000');
            $table->boolean('is_active')->default(true);
            $table->unique(['person_id', 'address'], 'dispatch_address_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dispatch_addresses');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
