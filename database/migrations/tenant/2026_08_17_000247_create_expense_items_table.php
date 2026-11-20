<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - expense_id: bigint
 * - description: varchar(255)
 * - total: numeric(30,12)
 * - calculation_snapshot: json
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_items', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('expense_id');
            $table->string('description', 255)->collation('sab_ci_ai');
            $table->decimal('total', 30, 12);
            $table->json('calculation_snapshot')->nullable()->comment('Exact decimal fractions v1');
            $table->index(['expense_id'], 'expense_items_expense_id_foreign');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_items');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
