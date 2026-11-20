<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - relation_id: bigint
 * - type: bigint
 * - relation_model: text
 * - file_line: text
 * - sendit: smallint
 * - email: text
 * - created_at: timestamp
 * - updated_at: timestamp
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_send_log', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('relation_id')->nullable()->default('0')->comment('Id de modelo');
            $table->bigInteger('type')->nullable()->default('0')->comment('Tipo de relacion');
            $table->text('relation_model')->collation('sab_ci_ai')->nullable()->comment('Modelo a relacion');
            $table->text('file_line')->collation('sab_ci_ai')->nullable()->comment('Archivo qu elo llama');
            $table->smallInteger('sendit')->nullable()->default('1')->comment('Booleano para envio de correo');
            $table->text('email')->collation('sab_ci_ai')->nullable()->comment('Correo de destino');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
        DB::statement('ALTER TABLE "email_send_log" ADD CONSTRAINT "email_send_log_relation_id_range" CHECK ("relation_id" >= 0)');
        DB::statement('ALTER TABLE "email_send_log" ADD CONSTRAINT "email_send_log_type_range" CHECK ("type" BETWEEN 0 AND 4294967295)');
        DB::statement('ALTER TABLE "email_send_log" ADD CONSTRAINT "email_send_log_sendit_range" CHECK ("sendit" BETWEEN 0 AND 255)');
    }

    public function down(): void
    {
        Schema::dropIfExists('email_send_log');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
