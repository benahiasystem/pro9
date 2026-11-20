<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - income_id: bigint
 * - description: varchar(255)
 * - total: numeric(30,12)
 * - calculation_snapshot: json
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('income_items', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('income_id');
            $table->string('description', 255)->collation('pro9_ci_ai');
            $table->decimal('total', 30, 12);
            $table->json('calculation_snapshot')->nullable()->comment('Exact decimal fractions v1');
            $table->index(['income_id'], 'income_items_income_id_foreign');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('income_items');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
