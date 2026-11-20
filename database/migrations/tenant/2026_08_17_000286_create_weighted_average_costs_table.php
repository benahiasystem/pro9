<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - date_of_issue: date
 * - origin_id: bigint
 * - origin_type: varchar(255)
 * - item_id: bigint
 * - quantity: numeric(30,12)
 * - cost: numeric(30,12)
 * - total: numeric(30,12)
 * - weighted_cost: numeric(30,12)
 * - stock: numeric(30,12)
 * - created_at: timestamp
 * - updated_at: timestamp
 * - calculation_snapshot: json
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('weighted_average_costs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->date('date_of_issue');
            $table->bigInteger('origin_id');
            $table->string('origin_type', 255)->collation('sab_ci_ai');
            $table->bigInteger('item_id');
            $table->decimal('quantity', 30, 12);
            $table->decimal('cost', 30, 12);
            $table->decimal('total', 30, 12);
            $table->decimal('weighted_cost', 30, 12);
            $table->decimal('stock', 30, 12);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->json('calculation_snapshot')->nullable()->comment('Exact decimal fractions v1');
            $table->index(['origin_id', 'origin_type'], 'weighted_average_costs_origin_index');
            $table->index(['item_id'], 'weighted_average_costs_item_id_foreign');
            $table->index(['date_of_issue'], 'weighted_average_costs_date_of_issue_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weighted_average_costs');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
