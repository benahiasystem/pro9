<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - user_id: bigint
 * - documentary_file_id: bigint
 * - documentary_office_id: bigint
 * - observation: text
 * - attached_file: text
 * - created_at: timestamp
 * - updated_at: timestamp
 * - documentary_guides_number_id: bigint
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documentary_files_archives', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('user_id')->default('0')->comment('usuario asociado');
            $table->bigInteger('documentary_file_id')->default('0')->comment('Solicitud asociada');
            $table->bigInteger('documentary_office_id')->default('0')->comment('etapa asociada');
            $table->text('observation')->collation('pro9_ci_ai')->nullable()->comment('observacion');
            $table->text('attached_file')->collation('pro9_ci_ai')->nullable()->comment('etapa asociada');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->bigInteger('documentary_guides_number_id')->default('0');
        });
        DB::statement('ALTER TABLE "documentary_files_archives" ADD CONSTRAINT "documentary_files_archives_user_id_range" CHECK ("user_id" >= 0)');
        DB::statement('ALTER TABLE "documentary_files_archives" ADD CONSTRAINT "documentary_files_archives_documentary_file_id_range" CHECK ("documentary_file_id" >= 0)');
        DB::statement('ALTER TABLE "documentary_files_archives" ADD CONSTRAINT "documentary_files_archives_documentary_office_id_range" CHECK ("documentary_office_id" >= 0)');
        DB::statement('ALTER TABLE "documentary_files_archives" ADD CONSTRAINT "documentary_files_archives_documentary_guides_number_id_range" CHECK ("documentary_guides_number_id" >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('documentary_files_archives');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
