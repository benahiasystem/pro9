<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - machine_type_id: bigint
 * - name: text
 * - brand: text
 * - model: text
 * - closing_force: text
 * - created_at: timestamp
 * - updated_at: timestamp
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('machines', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('machine_type_id');
            $table->text('name')->collation('sab_ci_ai')->nullable();
            $table->text('brand')->collation('sab_ci_ai')->nullable();
            $table->text('model')->collation('sab_ci_ai')->nullable();
            $table->text('closing_force')->collation('sab_ci_ai')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
        DB::statement('ALTER TABLE "machines" ADD CONSTRAINT "machines_machine_type_id_range" CHECK ("machine_type_id" >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('machines');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
