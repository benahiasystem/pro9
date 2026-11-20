<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - user_id: bigint
 * - documentary_document_id: bigint
 * - documentary_process_id: bigint
 * - number: text
 * - year: bigint
 * - invoice: text
 * - date_register: varchar(10)
 * - time_register: varchar(8)
 * - person_id: bigint
 * - sender: text
 * - subject: text
 * - attached_file: text
 * - observation: text
 * - status: varchar(255)
 * - documentary_office_id: bigint
 * - created_at: timestamp
 * - updated_at: timestamp
 * - requirements: text
 * - is_archive: smallint
 * - is_simplify: smallint
 * - documentary_guides_number_status_id: bigint
 * - is_completed: boolean
 * - establishment_id: bigint
 * - date_end: timestamp
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documentary_files', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('user_id')->nullable();
            $table->bigInteger('documentary_document_id')->nullable()->default('0');
            $table->bigInteger('documentary_process_id');
            $table->text('number')->collation('sab_ci_ai')->nullable();
            $table->bigInteger('year');
            $table->text('invoice')->collation('sab_ci_ai');
            $table->string('date_register', 10)->collation('sab_ci_ai');
            $table->string('time_register', 8)->collation('sab_ci_ai');
            $table->bigInteger('person_id');
            $table->text('sender')->collation('sab_ci_ai')->nullable();
            $table->text('subject')->collation('sab_ci_ai')->nullable();
            $table->text('attached_file')->collation('sab_ci_ai')->nullable();
            $table->text('observation')->collation('sab_ci_ai')->nullable();
            $table->string('status', 255)->collation('sab_ci_ai')->default('RECIBIDO');
            $table->bigInteger('documentary_office_id')->default('0')->comment('Define el ultimo proceso');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->text('requirements')->collation('sab_ci_ai')->nullable();
            $table->smallInteger('is_archive')->default('0')->comment('define si el tramite es simplificado');
            $table->smallInteger('is_simplify')->default('0')->comment('define si el tramite es simplificado');
            $table->bigInteger('documentary_guides_number_status_id')->default('0')->comment('Cuando es simplificado, se usará este status');
            $table->boolean('is_completed')->default(false);
            $table->bigInteger('establishment_id')->default('0');
            $table->timestamp('date_end')->nullable()->comment('Fecha de finalización');
            $table->index(['documentary_document_id'], 'documentary_files_documentary_document_id_foreign');
            $table->index(['documentary_process_id'], 'documentary_files_documentary_process_id_foreign');
            $table->index(['person_id'], 'documentary_files_person_id_foreign');
            $table->index(['user_id'], 'documentary_files_user_id_foreign');
        });
        DB::statement('ALTER TABLE "documentary_files" ADD CONSTRAINT "documentary_files_documentary_document_id_range" CHECK ("documentary_document_id" >= 0)');
        DB::statement('ALTER TABLE "documentary_files" ADD CONSTRAINT "documentary_files_year_range" CHECK ("year" BETWEEN 0 AND 4294967295)');
        DB::statement('ALTER TABLE "documentary_files" ADD CONSTRAINT "documentary_files_status_check" CHECK ("status" IN (\'RECIBIDO\',\'DERIVADO\',\'FINALIZADO\',\'ARCHIVADO\'))');
        DB::statement('ALTER TABLE "documentary_files" ADD CONSTRAINT "documentary_files_documentary_office_id_range" CHECK ("documentary_office_id" >= 0)');
        DB::statement('ALTER TABLE "documentary_files" ADD CONSTRAINT "documentary_files_is_archive_range" CHECK ("is_archive" BETWEEN 0 AND 255)');
        DB::statement('ALTER TABLE "documentary_files" ADD CONSTRAINT "documentary_files_is_simplify_range" CHECK ("is_simplify" BETWEEN 0 AND 255)');
        DB::statement('ALTER TABLE "documentary_files" ADD CONSTRAINT "documentary_files_documentary_guides_number_status_id_range" CHECK ("documentary_guides_number_status_id" >= 0)');
        DB::statement('ALTER TABLE "documentary_files" ADD CONSTRAINT "documentary_files_establishment_id_range" CHECK ("establishment_id" >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('documentary_files');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
