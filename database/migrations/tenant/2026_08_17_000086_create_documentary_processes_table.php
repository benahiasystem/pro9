<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - name: text
 * - description: text
 * - price: numeric(30,12)
 * - active: boolean
 * - created_at: timestamp
 * - updated_at: timestamp
 * - documentary_offices: text
 * - documentary_offices_order: text
 * - calculation_snapshot: json
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documentary_processes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->text('name')->collation('sab_ci_ai');
            $table->text('description')->collation('sab_ci_ai')->nullable();
            $table->decimal('price', 30, 12)->default('0.00000');
            $table->boolean('active')->default(true);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->text('documentary_offices')->collation('sab_ci_ai')->nullable()->comment('etapas que contiene');
            $table->text('documentary_offices_order')->collation('sab_ci_ai')->nullable();
            $table->json('calculation_snapshot')->nullable()->comment('Exact decimal fractions v1');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documentary_processes');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
