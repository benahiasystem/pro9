<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - doc_processes_id: bigint
 * - doc_offices_id: bigint
 * - active: smallint
 * - order: bigint
 * - created_at: timestamp
 * - updated_at: timestamp
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documentary_processes_rel_off', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('doc_processes_id')->nullable()->default('0')->comment('Requerimiento relacionado');
            $table->bigInteger('doc_offices_id')->nullable()->default('0')->comment('Proceso relacionado');
            $table->smallInteger('active')->default('0')->comment('Status de la relacion');
            $table->bigInteger('order')->nullable()->default('1')->comment('Establece el orden del proceso');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
        DB::statement('ALTER TABLE "documentary_processes_rel_off" ADD CONSTRAINT "documentary_processes_rel_off_doc_processes_id_range" CHECK ("doc_processes_id" >= 0)');
        DB::statement('ALTER TABLE "documentary_processes_rel_off" ADD CONSTRAINT "documentary_processes_rel_off_doc_offices_id_range" CHECK ("doc_offices_id" >= 0)');
        DB::statement('ALTER TABLE "documentary_processes_rel_off" ADD CONSTRAINT "documentary_processes_rel_off_active_range" CHECK ("active" BETWEEN 0 AND 255)');
        DB::statement('ALTER TABLE "documentary_processes_rel_off" ADD CONSTRAINT "documentary_processes_rel_off_order_range" CHECK ("order" BETWEEN 0 AND 4294967295)');
    }

    public function down(): void
    {
        Schema::dropIfExists('documentary_processes_rel_off');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
