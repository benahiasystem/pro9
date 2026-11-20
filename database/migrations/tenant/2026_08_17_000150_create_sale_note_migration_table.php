<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - sale_notes_id: bigint
 * - user_id: bigint
 * - success: smallint
 * - url: varchar(255)
 * - remote_id: bigint
 * - number: varchar(255)
 * - data: text
 * - created_at: timestamp
 * - updated_at: timestamp
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_note_migration', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('sale_notes_id');
            $table->bigInteger('user_id');
            $table->smallInteger('success')->default('0');
            $table->string('url', 255)->collation('pro9_ci_ai');
            $table->bigInteger('remote_id')->default('0');
            $table->string('number', 255)->collation('pro9_ci_ai')->nullable();
            $table->text('data')->collation('pro9_ci_ai')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
        DB::statement('ALTER TABLE "sale_note_migration" ADD CONSTRAINT "sale_note_migration_sale_notes_id_range" CHECK ("sale_notes_id" >= 0)');
        DB::statement('ALTER TABLE "sale_note_migration" ADD CONSTRAINT "sale_note_migration_user_id_range" CHECK ("user_id" >= 0)');
        DB::statement('ALTER TABLE "sale_note_migration" ADD CONSTRAINT "sale_note_migration_success_range" CHECK ("success" BETWEEN 0 AND 255)');
        DB::statement('ALTER TABLE "sale_note_migration" ADD CONSTRAINT "sale_note_migration_remote_id_range" CHECK ("remote_id" >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_note_migration');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
