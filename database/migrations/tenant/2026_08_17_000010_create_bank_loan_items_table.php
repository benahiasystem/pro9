<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - bank_loan_id: bigint
 * - description: text
 * - total: numeric(30,12)
 * - total_interest: numeric(30,12)
 * - total_ingress: numeric(30,12)
 * - calculation_snapshot: json
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_loan_items', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('bank_loan_id');
            $table->text('description')->collation('pro9_ci_ai');
            $table->decimal('total', 30, 12)->nullable()->default('0.00');
            $table->decimal('total_interest', 30, 12)->nullable()->default('0.00');
            $table->decimal('total_ingress', 30, 12)->nullable()->default('0.00');
            $table->json('calculation_snapshot')->nullable()->comment('Exact decimal fractions v1');
        });
        DB::statement('ALTER TABLE "bank_loan_items" ADD CONSTRAINT "bank_loan_items_bank_loan_id_range" CHECK ("bank_loan_id" >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_loan_items');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
