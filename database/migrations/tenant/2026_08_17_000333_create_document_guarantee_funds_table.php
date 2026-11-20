<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - document_id: bigint
 * - amount: numeric(30,12)
 * - base: numeric(30,12)
 * - percentage: numeric(30,12)
 * - created_at: timestamp
 * - updated_at: timestamp
 * - calculation_snapshot: json
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_guarantee_funds', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('document_id');
            $table->decimal('amount', 30, 12)->default('0');
            $table->decimal('base', 30, 12)->default('0');
            $table->decimal('percentage', 30, 12)->default('0');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->json('calculation_snapshot')->nullable()->comment('Exact decimal fractions v1');
            $table->unique(['document_id'], 'document_guarantee_funds_document_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_guarantee_funds');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
