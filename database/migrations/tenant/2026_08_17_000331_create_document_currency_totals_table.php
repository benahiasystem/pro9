<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - document_id: bigint
 * - currency_type_id: varchar(255)
 * - taxed: numeric(30,12)
 * - exportation: numeric(30,12)
 * - exempt: numeric(30,12)
 * - discount: numeric(30,12)
 * - charge: numeric(30,12)
 * - iva: numeric(30,12)
 * - other_taxes: numeric(30,12)
 * - total: numeric(30,12)
 * - tax_breakdown: json
 * - created_at: timestamp
 * - updated_at: timestamp
 * - calculation_snapshot: json
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_currency_totals', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('document_id');
            $table->string('currency_type_id', 255)->collation('pro9_ci_ai');
            $table->decimal('taxed', 30, 12)->default('0');
            $table->decimal('exportation', 30, 12)->default('0');
            $table->decimal('exempt', 30, 12)->default('0');
            $table->decimal('discount', 30, 12)->default('0');
            $table->decimal('charge', 30, 12)->default('0');
            $table->decimal('iva', 30, 12)->default('0');
            $table->decimal('other_taxes', 30, 12)->default('0');
            $table->decimal('total', 30, 12)->default('0');
            $table->json('tax_breakdown');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->json('calculation_snapshot')->nullable()->comment('Exact decimal fractions v1');
            $table->unique(['document_id'], 'document_currency_totals_document_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_currency_totals');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
