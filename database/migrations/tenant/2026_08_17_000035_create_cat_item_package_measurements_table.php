<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - name: text
 * - created_at: timestamp
 * - updated_at: timestamp
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cat_item_package_measurements', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->text('name')->collation('pro9_ci_ai')->comment('Nombre de medidas del paquete');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cat_item_package_measurements');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
