<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - stock_control: boolean
 * - validate_stock_add_item: boolean
 * - inventory_review: boolean
 * - created_at: timestamp
 * - updated_at: timestamp
 * - generate_internal_id: boolean
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_configurations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->boolean('stock_control')->default(false);
            $table->boolean('validate_stock_add_item')->default(false);
            $table->boolean('inventory_review')->default(false);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->boolean('generate_internal_id')->default(true);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_configurations');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
