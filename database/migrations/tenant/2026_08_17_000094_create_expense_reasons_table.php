<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - description: varchar(255)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_reasons', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('description', 255)->collation('pro9_ci_ai');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_reasons');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
