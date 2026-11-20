<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: smallint
 * - code: varchar(2)
 * - description: varchar(255)
 * - active: boolean
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cat_providers_types', function (Blueprint $table) {
            $table->smallInteger('id');
            $table->string('code', 2)->collation('pro9_ci_ai')->nullable();
            $table->string('description', 255)->collation('pro9_ci_ai');
            $table->boolean('active');
            $table->primary(['id']);
            $table->unique(['code'], 'cat_providers_types_code_unique');
        });
        DB::statement('ALTER TABLE "cat_providers_types" ADD CONSTRAINT "cat_providers_types_id_range" CHECK ("id" BETWEEN 0 AND 255)');
    }

    public function down(): void
    {
        Schema::dropIfExists('cat_providers_types');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
