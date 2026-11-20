<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - value: varchar(255)
 * - person_id: bigint
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plates', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('value', 255)->collation('pro9_ci_ai');
            $table->bigInteger('person_id');
            $table->index(['person_id'], 'plates_person_id_foreign');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plates');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
