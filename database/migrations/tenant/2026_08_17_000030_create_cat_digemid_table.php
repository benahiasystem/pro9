<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - item_id: bigint
 * - cod_digemid: text
 * - nom_prod: text
 * - concent: text
 * - nom_form_farm: text
 * - nom_form_farm_simplif: text
 * - presentac: text
 * - fracciones: text
 * - fec_vcto_reg_sanitario: text
 * - num_reg_san: text
 * - nom_titular: text
 * - prices: text
 * - max_prices: bigint
 * - active: smallint
 * - last_update: timestamp
 * - created_at: timestamp
 * - updated_at: timestamp
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cat_digemid', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('item_id')->comment('Id de la tabla de item');
            $table->text('cod_digemid')->collation('sab_ci_ai')->comment('Codigo digmid');
            $table->text('nom_prod')->collation('sab_ci_ai')->nullable()->comment('Nombre segun digemid');
            $table->text('concent')->collation('sab_ci_ai')->nullable()->comment('Dosificacion segun digemid');
            $table->text('nom_form_farm')->collation('sab_ci_ai')->nullable();
            $table->text('nom_form_farm_simplif')->collation('sab_ci_ai')->nullable();
            $table->text('presentac')->collation('sab_ci_ai')->nullable();
            $table->text('fracciones')->collation('sab_ci_ai')->nullable();
            $table->text('fec_vcto_reg_sanitario')->collation('sab_ci_ai')->nullable();
            $table->text('num_reg_san')->collation('sab_ci_ai')->nullable();
            $table->text('nom_titular')->collation('sab_ci_ai')->nullable();
            $table->text('prices')->collation('sab_ci_ai')->nullable();
            $table->bigInteger('max_prices')->nullable()->default('0');
            $table->smallInteger('active')->default('0');
            $table->timestamp('last_update')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
        DB::statement('ALTER TABLE "cat_digemid" ADD CONSTRAINT "cat_digemid_item_id_range" CHECK ("item_id" >= 0)');
        DB::statement('ALTER TABLE "cat_digemid" ADD CONSTRAINT "cat_digemid_max_prices_range" CHECK ("max_prices" BETWEEN 0 AND 4294967295)');
        DB::statement('ALTER TABLE "cat_digemid" ADD CONSTRAINT "cat_digemid_active_range" CHECK ("active" BETWEEN 0 AND 255)');
    }

    public function down(): void
    {
        Schema::dropIfExists('cat_digemid');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
