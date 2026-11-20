<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - active: smallint
 * - user_id: bigint
 * - documentary_office_id: bigint
 * - created_at: timestamp
 * - updated_at: timestamp
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rel_user_to_documentary_offices', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->smallInteger('active')->default('0');
            $table->bigInteger('user_id')->default('0')->comment('usuario asociado');
            $table->bigInteger('documentary_office_id')->default('0')->comment('etapa asociada');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
        DB::statement('ALTER TABLE "rel_user_to_documentary_offices" ADD CONSTRAINT "rel_user_to_documentary_offices_active_range" CHECK ("active" BETWEEN 0 AND 255)');
        DB::statement('ALTER TABLE "rel_user_to_documentary_offices" ADD CONSTRAINT "rel_user_to_documentary_offices_user_id_range" CHECK ("user_id" >= 0)');
        DB::statement('ALTER TABLE "rel_user_to_documentary_offices" ADD CONSTRAINT "rel_user_to_documentary_offices_documentary_office_id_range" CHECK ("documentary_office_id" >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('rel_user_to_documentary_offices');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
