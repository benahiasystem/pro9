<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - documentary_file_id: bigint
 * - documentary_office_id: bigint
 * - documentary_action_id: bigint
 * - status: varchar(255)
 * - created_at: timestamp
 * - updated_at: timestamp
 * - office_name: text
 * - process_name: text
 * - documentary_process_id: bigint
 * - complete: integer
 * - start_date: timestamp
 * - end_date: timestamp
 * - days: bigint
 * - observation: integer
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documentary_file_offices', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('documentary_file_id');
            $table->bigInteger('documentary_office_id');
            $table->bigInteger('documentary_action_id')->default('0');
            $table->string('status', 255)->collation('sab_ci_ai')->default('POR DERIVAR');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->text('office_name')->collation('sab_ci_ai')->nullable()->comment('Nombre de la etapa');
            $table->text('process_name')->collation('sab_ci_ai')->nullable()->comment('Nombre del tramite');
            $table->bigInteger('documentary_process_id')->default('0')->comment('Tramite relacionado');
            $table->integer('complete')->default('0')->comment('Define si la etapa esta completa');
            $table->timestamp('start_date')->nullable()->comment('Fecha de inicio');
            $table->timestamp('end_date')->nullable()->comment('Fecha de finalizacion');
            $table->bigInteger('days')->nullable()->default('0')->comment('dias para el tramite');
            $table->integer('observation');
            $table->index(['documentary_file_id'], 'documentary_file_offices_documentary_file_id_foreign');
            $table->index(['documentary_office_id'], 'documentary_file_offices_documentary_office_id_foreign');
            $table->index(['documentary_action_id'], 'documentary_file_offices_documentary_action_id_foreign');
        });
        DB::statement('ALTER TABLE "documentary_file_offices" ADD CONSTRAINT "documentary_file_offices_documentary_file_id_range" CHECK ("documentary_file_id" >= 0)');
        DB::statement('ALTER TABLE "documentary_file_offices" ADD CONSTRAINT "documentary_file_offices_documentary_office_id_range" CHECK ("documentary_office_id" >= 0)');
        DB::statement('ALTER TABLE "documentary_file_offices" ADD CONSTRAINT "documentary_file_offices_documentary_action_id_range" CHECK ("documentary_action_id" >= 0)');
        DB::statement('ALTER TABLE "documentary_file_offices" ADD CONSTRAINT "documentary_file_offices_status_check" CHECK ("status" IN (\'POR DERIVAR\',\'POR RECIBIR\',\'EN PROCESO\',\'FINALIZADO\',\'ARCHIVADO\'))');
        DB::statement('ALTER TABLE "documentary_file_offices" ADD CONSTRAINT "documentary_file_offices_documentary_process_id_range" CHECK ("documentary_process_id" >= 0)');
        DB::statement('ALTER TABLE "documentary_file_offices" ADD CONSTRAINT "documentary_file_offices_complete_range" CHECK ("complete" BETWEEN -2147483648 AND 2147483647)');
        DB::statement('ALTER TABLE "documentary_file_offices" ADD CONSTRAINT "documentary_file_offices_days_range" CHECK ("days" BETWEEN 0 AND 4294967295)');
        DB::statement('ALTER TABLE "documentary_file_offices" ADD CONSTRAINT "documentary_file_offices_observation_range" CHECK ("observation" BETWEEN -2147483648 AND 2147483647)');
    }

    public function down(): void
    {
        Schema::dropIfExists('documentary_file_offices');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
