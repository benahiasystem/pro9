<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - value: varchar(255)
 * - description: varchar(255)
 * - order_menu: integer
 * - created_at: timestamp
 * - updated_at: timestamp
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_modules', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('value', 255)->collation('sab_ci_ai');
            $table->string('description', 255)->collation('sab_ci_ai');
            $table->integer('order_menu');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['value'], 'app_modules_value_unique');
        });
        DB::statement('ALTER TABLE "app_modules" ADD CONSTRAINT "app_modules_order_menu_range" CHECK ("order_menu" BETWEEN -2147483648 AND 2147483647)');
    }

    public function down(): void
    {
        Schema::dropIfExists('app_modules');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
