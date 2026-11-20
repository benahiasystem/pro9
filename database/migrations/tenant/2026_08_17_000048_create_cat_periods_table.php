<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - period: char(1)
 * - name: text
 * - active: smallint
 * - created_at: timestamp
 * - updated_at: timestamp
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cat_periods', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->char('period', 1)->collation('sab_ci_ai')->comment('Define si es dia, mes o año - D/M/Y');
            $table->text('name')->collation('sab_ci_ai')->comment('Nombre del periodo');
            $table->smallInteger('active')->default('0')->comment('Si esta activo');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
        DB::statement('ALTER TABLE "cat_periods" ADD CONSTRAINT "cat_periods_active_range" CHECK ("active" BETWEEN -128 AND 127)');
    }

    public function down(): void
    {
        Schema::dropIfExists('cat_periods');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
