<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: varchar(16)
 * - description: varchar(255)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fiscal_environments', function (Blueprint $table) {
            $table->string('id', 16)->collation('sab_ci_ai');
            $table->string('description', 255)->collation('sab_ci_ai');
            $table->primary(['id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fiscal_environments');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
