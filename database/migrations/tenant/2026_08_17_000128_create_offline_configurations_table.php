<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - is_client: boolean
 * - token_server: varchar(255)
 * - url_server: varchar(255)
 * - created_at: timestamp
 * - updated_at: timestamp
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offline_configurations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->boolean('is_client')->default(false);
            $table->string('token_server', 255)->collation('C')->nullable();
            $table->string('url_server', 255)->collation('pro9_ci_ai')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offline_configurations');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
