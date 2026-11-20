<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - ruc: varchar(255)
 * - nombre_razon_social: varchar(155)
 * - estado_contribuyente: varchar(100)
 * - condicion_domicilio: varchar(100)
 * - ubigeo: varchar(50)
 * - tipo_via: varchar(20)
 * - nombre_via: varchar(50)
 * - codigo_zona: varchar(155)
 * - tipo_zona: varchar(20)
 * - numero: varchar(155)
 * - interior: varchar(50)
 * - lote: varchar(20)
 * - departamento: varchar(100)
 * - manzana: varchar(20)
 * - kilometro: varchar(20)
 * - status: smallint
 * - created_at: timestamp
 * - updated_at: timestamp
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('padrones', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('ruc', 255)->collation('pro9_ci_ai');
            $table->string('nombre_razon_social', 155)->collation('pro9_ci_ai');
            $table->string('estado_contribuyente', 100)->collation('pro9_ci_ai');
            $table->string('condicion_domicilio', 100)->collation('pro9_ci_ai');
            $table->string('ubigeo', 50)->collation('pro9_ci_ai');
            $table->string('tipo_via', 20)->collation('pro9_ci_ai');
            $table->string('nombre_via', 50)->collation('pro9_ci_ai');
            $table->string('codigo_zona', 155)->collation('pro9_ci_ai');
            $table->string('tipo_zona', 20)->collation('pro9_ci_ai');
            $table->string('numero', 155)->collation('pro9_ci_ai');
            $table->string('interior', 50)->collation('pro9_ci_ai');
            $table->string('lote', 20)->collation('pro9_ci_ai');
            $table->string('departamento', 100)->collation('pro9_ci_ai');
            $table->string('manzana', 20)->collation('pro9_ci_ai');
            $table->string('kilometro', 20)->collation('pro9_ci_ai');
            $table->smallInteger('status')->default('1');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index(['ruc'], 'padrones_ruc_index');
        });
        DB::statement('ALTER TABLE "padrones" ADD CONSTRAINT "padrones_status_range" CHECK ("status" BETWEEN -128 AND 127)');
    }

    public function down(): void
    {
        Schema::dropIfExists('padrones');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
