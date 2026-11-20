<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - name: varchar(100)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suscription_grade', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 100)->collation('sab_ci_ai');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suscription_grade');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
