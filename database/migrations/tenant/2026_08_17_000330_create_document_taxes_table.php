<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - document_id: bigint
 * - document_payment_id: bigint
 * - tax_kind: varchar(16)
 * - code: varchar(32)
 * - percentage: numeric(30,12)
 * - base: numeric(30,12)
 * - amount: numeric(30,12)
 * - base_ves: numeric(30,12)
 * - amount_ves: numeric(30,12)
 * - created_at: timestamp
 * - updated_at: timestamp
 * - calculation_snapshot: json
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_taxes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('document_id');
            $table->bigInteger('document_payment_id')->nullable();
            $table->string('tax_kind', 16)->collation('pro9_ci_ai');
            $table->string('code', 32)->collation('pro9_ci_ai');
            $table->decimal('percentage', 30, 12)->default('0');
            $table->decimal('base', 30, 12)->default('0');
            $table->decimal('amount', 30, 12)->default('0');
            $table->decimal('base_ves', 30, 12)->default('0');
            $table->decimal('amount_ves', 30, 12)->default('0');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->json('calculation_snapshot')->nullable()->comment('Exact decimal fractions v1');
            $table->unique(['document_payment_id'], 'document_taxes_payment_unique');
            $table->index(['document_id'], 'document_taxes_document_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_taxes');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
