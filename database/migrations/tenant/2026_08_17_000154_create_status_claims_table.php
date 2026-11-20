<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - description: varchar(60)
 * - color: varchar(7)
 * - sort_order: integer
 * - is_initial: boolean
 * - is_final: boolean
 * - action_send_email: boolean
 * - assigned_user_id: bigint
 * - created_at: timestamp
 * - updated_at: timestamp
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('status_claims', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('description', 60)->collation('pro9_ci_ai');
            $table->string('color', 7)->collation('pro9_ci_ai')->default('#909399');
            $table->integer('sort_order')->default('0');
            $table->boolean('is_initial')->default(false);
            $table->boolean('is_final')->default(false);
            $table->boolean('action_send_email')->default(false);
            $table->bigInteger('assigned_user_id')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
        DB::statement('ALTER TABLE "status_claims" ADD CONSTRAINT "status_claims_sort_order_range" CHECK ("sort_order" BETWEEN 0 AND 65535)');
        DB::statement('ALTER TABLE "status_claims" ADD CONSTRAINT "status_claims_assigned_user_id_range" CHECK ("assigned_user_id" >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('status_claims');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
