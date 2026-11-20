<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: varchar(255)
 * - name: varchar(255)
 * - days: integer
 * - is_locked: boolean
 * - is_active: boolean
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_conditions', function (Blueprint $table) {
            $table->string('id', 255)->collation('sab_ci_ai');
            $table->string('name', 255)->collation('sab_ci_ai');
            $table->integer('days')->default('0');
            $table->boolean('is_locked')->default(false);
            $table->boolean('is_active')->default(true);
            $table->index(['id'], 'payment_conditions_id_index');
            $table->unique(['id'], 'payment_conditions_id_pg_unique');
        });
        DB::statement('ALTER TABLE "payment_conditions" ADD CONSTRAINT "payment_conditions_days_range" CHECK ("days" BETWEEN -2147483648 AND 2147483647)');
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_conditions');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
