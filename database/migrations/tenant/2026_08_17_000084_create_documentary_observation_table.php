<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - doc_file_id: bigint
 * - observation: text
 * - created_at: timestamp
 * - updated_at: timestamp
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documentary_observation', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('doc_file_id')->nullable()->default('0')->comment('Expediente relacionado');
            $table->text('observation')->collation('pro9_ci_ai')->nullable()->comment('Conjunto de etapas.');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
        DB::statement('ALTER TABLE "documentary_observation" ADD CONSTRAINT "documentary_observation_doc_file_id_range" CHECK ("doc_file_id" >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('documentary_observation');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
