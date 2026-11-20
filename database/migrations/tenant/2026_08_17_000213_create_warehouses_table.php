<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - establishment_id: bigint
 * - description: varchar(255)
 * - created_at: timestamp
 * - updated_at: timestamp
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouses', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('establishment_id');
            $table->string('description', 255)->collation('sab_ci_ai');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index(['establishment_id'], 'warehouses_establishment_id_foreign');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouses');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
