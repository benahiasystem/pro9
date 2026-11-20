<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - code: varchar(255)
 * - quantity: numeric(30,12)
 * - old_quantity: numeric(30,12)
 * - date_of_due: date
 * - item_id: bigint
 * - created_at: timestamp
 * - updated_at: timestamp
 * - calculation_snapshot: json
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_lots_group', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('code', 255)->collation('pro9_ci_ai');
            $table->decimal('quantity', 30, 12);
            $table->decimal('old_quantity', 30, 12)->default('0.0000');
            $table->date('date_of_due');
            $table->bigInteger('item_id');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->json('calculation_snapshot')->nullable()->comment('Exact decimal fractions v1');
            $table->index(['item_id'], 'item_lots_group_item_id_foreign');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_lots_group');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
