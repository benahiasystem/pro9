<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - doc_file_id: bigint
 * - doc_office_id: bigint
 * - guide: text
 * - origin: text
 * - created_at: timestamp
 * - updated_at: timestamp
 * - date_of_due: timestamp
 * - observation: text
 * - description: text
 * - date_take: timestamp
 * - date_end: timestamp
 * - documentary_guides_number_status_id: bigint
 * - user_id: bigint
 * - total_day: bigint
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documentary_guides_number', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('doc_file_id')->nullable()->default('0')->comment('Expediente relacionado');
            $table->bigInteger('doc_office_id')->nullable()->default('0')->comment('Etapa observada');
            $table->text('guide')->collation('pro9_ci_ai')->nullable()->comment('Especifica la guia');
            $table->text('origin')->collation('pro9_ci_ai')->nullable()->comment('Especifica la instucion');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('date_of_due')->nullable();
            $table->text('observation')->collation('pro9_ci_ai')->nullable();
            $table->text('description')->collation('pro9_ci_ai')->nullable();
            $table->timestamp('date_take')->nullable()->comment('Fecha estimada de finalización');
            $table->timestamp('date_end')->nullable()->comment('Fecha de finalización');
            $table->bigInteger('documentary_guides_number_status_id')->nullable()->default('0')->comment('relacionado con documentary_guides_number_status');
            $table->bigInteger('user_id')->default('0')->comment('Responsable');
            $table->bigInteger('total_day')->nullable()->default('1');
        });
        DB::statement('ALTER TABLE "documentary_guides_number" ADD CONSTRAINT "documentary_guides_number_doc_file_id_range" CHECK ("doc_file_id" >= 0)');
        DB::statement('ALTER TABLE "documentary_guides_number" ADD CONSTRAINT "documentary_guides_number_doc_office_id_range" CHECK ("doc_office_id" >= 0)');
        DB::statement('ALTER TABLE "documentary_guides_number" ADD CONSTRAINT "documentary_guides_number_documentary_guides_numbe_2af46e4a58d9" CHECK ("documentary_guides_number_status_id" >= 0)');
        DB::statement('ALTER TABLE "documentary_guides_number" ADD CONSTRAINT "documentary_guides_number_user_id_range" CHECK ("user_id" >= 0)');
        DB::statement('ALTER TABLE "documentary_guides_number" ADD CONSTRAINT "documentary_guides_number_total_day_range" CHECK ("total_day" BETWEEN 0 AND 4294967295)');
    }

    public function down(): void
    {
        Schema::dropIfExists('documentary_guides_number');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
