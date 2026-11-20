<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: varchar(255)
 * - active: boolean
 * - base: boolean
 * - level: varchar(255)
 * - type: varchar(255)
 * - description: varchar(255)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cat_charge_discount_types', function (Blueprint $table) {
            $table->string('id', 255)->collation('sab_ci_ai');
            $table->boolean('active');
            $table->boolean('base');
            $table->string('level', 255)->collation('sab_ci_ai');
            $table->string('type', 255)->collation('sab_ci_ai');
            $table->string('description', 255)->collation('sab_ci_ai');
            $table->index(['id'], 'cat_charge_discount_types_id_index');
        });
        DB::statement('ALTER TABLE "cat_charge_discount_types" ADD CONSTRAINT "cat_charge_discount_types_level_check" CHECK ("level" IN (\'item\',\'global\'))');
        DB::statement('ALTER TABLE "cat_charge_discount_types" ADD CONSTRAINT "cat_charge_discount_types_type_check" CHECK ("type" IN (\'discount\',\'charge\'))');
    }

    public function down(): void
    {
        Schema::dropIfExists('cat_charge_discount_types');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
