<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - establishment_id: bigint
 * - template_name: varchar(255)
 * - columns_config: json
 * - created_at: timestamp
 * - updated_at: timestamp
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('template_columns_config', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('establishment_id');
            $table->string('template_name', 255)->collation('pro9_ci_ai')->default('Plantilla_personalizable');
            $table->json('columns_config')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['establishment_id', 'template_name'], 'template_columns_config_establishment_id_template_name_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('template_columns_config');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
