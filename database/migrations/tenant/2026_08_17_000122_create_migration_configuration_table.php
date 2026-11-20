<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - url: varchar(255)
 * - api_key: varchar(255)
 * - created_at: timestamp
 * - updated_at: timestamp
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('migration_configuration', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('url', 255)->collation('sab_ci_ai')->nullable();
            $table->string('api_key', 255)->collation('C')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('migration_configuration');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
