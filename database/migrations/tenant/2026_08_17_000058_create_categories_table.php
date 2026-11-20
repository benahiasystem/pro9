<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - name: varchar(255)
 * - image: varchar(255)
 * - created_at: timestamp
 * - updated_at: timestamp
 * - cuenta_compra: varchar(255)
 * - cuenta_venta: varchar(255)
 * - cuenta_compra_devolucion: varchar(255)
 * - cuenta_venta_devolucion: varchar(255)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 255)->collation('sab_ci_ai');
            $table->string('image', 255)->collation('sab_ci_ai')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->string('cuenta_compra', 255)->collation('sab_ci_ai')->nullable();
            $table->string('cuenta_venta', 255)->collation('sab_ci_ai')->nullable();
            $table->string('cuenta_compra_devolucion', 255)->collation('sab_ci_ai')->nullable();
            $table->string('cuenta_venta_devolucion', 255)->collation('sab_ci_ai')->nullable();
            $table->index(['name'], 'categories_name_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
