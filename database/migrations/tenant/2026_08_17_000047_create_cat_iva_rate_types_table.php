<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: varchar(4)
 * - description: varchar(255)
 * - percentage: numeric(30,12)
 * - tax_kind: varchar(16)
 * - calculation_snapshot: json
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cat_iva_rate_types', function (Blueprint $table) {
            $table->string('id', 4)->collation('sab_ci_ai');
            $table->string('description', 255)->collation('sab_ci_ai');
            $table->decimal('percentage', 30, 12);
            $table->string('tax_kind', 16)->collation('sab_ci_ai');
            $table->json('calculation_snapshot')->nullable()->comment('Exact decimal fractions v1');
            $table->primary(['id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cat_iva_rate_types');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
