<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - position: smallint
 * - label: varchar(50)
 * - is_active: boolean
 * - is_default: boolean
 * - created_at: timestamp
 * - updated_at: timestamp
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_labels', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->smallInteger('position')->comment('Orden de visualización del precio');
            $table->string('label', 50)->collation('sab_ci_ai')->comment('Etiqueta personalizada del precio (ej: Precio Mayorista)');
            $table->boolean('is_active')->default(true)->comment('Si está activo para usar en ventas');
            $table->boolean('is_default')->default(false);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['position'], 'price_labels_position_unique');
            $table->index(['is_active', 'position'], 'price_labels_is_active_position_index');
        });
        DB::statement('ALTER TABLE "price_labels" ADD CONSTRAINT "price_labels_position_range" CHECK ("position" BETWEEN -128 AND 127)');
    }

    public function down(): void
    {
        Schema::dropIfExists('price_labels');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
