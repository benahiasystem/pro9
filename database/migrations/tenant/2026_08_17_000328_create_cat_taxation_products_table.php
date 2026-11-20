<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: smallint
 * - description: varchar(255)
 * - active: boolean
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cat_taxation_products', function (Blueprint $table) {
            $table->smallInteger('id');
            $table->string('description', 255)->collation('pro9_ci_ai');
            $table->boolean('active');
            $table->primary(['id']);
        });
        DB::statement('ALTER TABLE "cat_taxation_products" ADD CONSTRAINT "cat_taxation_products_id_range" CHECK ("id" BETWEEN 0 AND 255)');
    }

    public function down(): void
    {
        Schema::dropIfExists('cat_taxation_products');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
