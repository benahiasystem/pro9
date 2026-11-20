<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - name: varchar(150)
 * - price: numeric(30,12)
 * - specify_zone: boolean
 * - active: boolean
 * - created_at: timestamp
 * - updated_at: timestamp
 * - calculation_snapshot: json
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_zones', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 150)->collation('pro9_ci_ai');
            $table->decimal('price', 30, 12)->default('0.00');
            $table->boolean('specify_zone')->default(false);
            $table->boolean('active')->default(true);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->json('calculation_snapshot')->nullable()->comment('Exact decimal fractions v1');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_zones');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
