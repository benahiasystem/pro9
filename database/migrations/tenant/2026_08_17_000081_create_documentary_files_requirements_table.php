<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - name: text
 * - file: smallint
 * - created_at: timestamp
 * - updated_at: timestamp
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documentary_files_requirements', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->text('name')->collation('pro9_ci_ai')->nullable()->comment('Nombre a mostrar');
            $table->smallInteger('file')->default('0')->comment('Define si tiene archivo');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
        DB::statement('ALTER TABLE "documentary_files_requirements" ADD CONSTRAINT "documentary_files_requirements_file_range" CHECK ("file" BETWEEN 0 AND 255)');
    }

    public function down(): void
    {
        Schema::dropIfExists('documentary_files_requirements');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
