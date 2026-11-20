<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - name: varchar(255)
 * - cost: numeric(30,12)
 * - unit_type_id: varchar(255)
 * - waste_percentage: numeric(30,12)
 * - stock: numeric(30,12)
 * - minimum_stock: numeric(30,12)
 * - created_at: timestamp
 * - updated_at: timestamp
 * - calculation_snapshot: json
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplies', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 255)->collation('pro9_ci_ai');
            $table->decimal('cost', 30, 12)->default('0.00');
            $table->string('unit_type_id', 255)->collation('pro9_ci_ai');
            $table->decimal('waste_percentage', 30, 12)->default('0.00');
            $table->decimal('stock', 30, 12)->default('0.0000');
            $table->decimal('minimum_stock', 30, 12)->default('0.0000');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->json('calculation_snapshot')->nullable()->comment('Exact decimal fractions v1');
            $table->index(['unit_type_id'], 'supplies_unit_type_id_foreign');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplies');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
