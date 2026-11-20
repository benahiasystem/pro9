<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: varchar(255)
 * - name: varchar(255)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_error_types', function (Blueprint $table) {
            $table->string('id', 255)->collation('pro9_ci_ai');
            $table->string('name', 255)->collation('pro9_ci_ai');
            $table->primary(['id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_error_types');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
