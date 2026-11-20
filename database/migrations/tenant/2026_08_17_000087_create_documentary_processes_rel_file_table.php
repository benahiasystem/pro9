<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - doc_processes_id: bigint
 * - doc_file_id: bigint
 * - doc_office_id: bigint
 * - stages: text
 * - complete: smallint
 * - created_at: timestamp
 * - updated_at: timestamp
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documentary_processes_rel_file', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('doc_processes_id')->nullable()->default('0')->comment('Requerimiento relacionado');
            $table->bigInteger('doc_file_id')->nullable()->default('0')->comment('Expediente relacionado');
            $table->bigInteger('doc_office_id')->nullable()->default('0')->comment('Etapa actual');
            $table->text('stages')->collation('pro9_ci_ai')->nullable()->comment('Conjunto de etapas.');
            $table->smallInteger('complete')->default('0')->comment('Define si se ha completado');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
        DB::statement('ALTER TABLE "documentary_processes_rel_file" ADD CONSTRAINT "documentary_processes_rel_file_doc_processes_id_range" CHECK ("doc_processes_id" >= 0)');
        DB::statement('ALTER TABLE "documentary_processes_rel_file" ADD CONSTRAINT "documentary_processes_rel_file_doc_file_id_range" CHECK ("doc_file_id" >= 0)');
        DB::statement('ALTER TABLE "documentary_processes_rel_file" ADD CONSTRAINT "documentary_processes_rel_file_doc_office_id_range" CHECK ("doc_office_id" >= 0)');
        DB::statement('ALTER TABLE "documentary_processes_rel_file" ADD CONSTRAINT "documentary_processes_rel_file_complete_range" CHECK ("complete" BETWEEN 0 AND 255)');
    }

    public function down(): void
    {
        Schema::dropIfExists('documentary_processes_rel_file');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
