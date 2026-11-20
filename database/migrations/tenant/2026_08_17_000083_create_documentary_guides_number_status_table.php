<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - name: varchar(255)
 * - color: varchar(255)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documentary_guides_number_status', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 255)->collation('pro9_ci_ai');
            $table->string('color', 255)->collation('pro9_ci_ai')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documentary_guides_number_status');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
