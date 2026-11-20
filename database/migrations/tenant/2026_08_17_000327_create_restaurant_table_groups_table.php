<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - main_table_id: bigint
 * - status: varchar(255)
 * - opening_date: timestamp
 * - total: numeric(30,12)
 * - created_at: timestamp
 * - updated_at: timestamp
 * - calculation_snapshot: json
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restaurant_table_groups', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('main_table_id')->nullable();
            $table->string('status', 255)->collation('sab_ci_ai')->default('open');
            $table->timestamp('opening_date')->nullable();
            $table->decimal('total', 30, 12)->default('0.00');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->json('calculation_snapshot')->nullable()->comment('Exact decimal fractions v1');
            $table->index(['main_table_id'], 'restaurant_table_groups_main_table_id_foreign');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_table_groups');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
