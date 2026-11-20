<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - cash_id: bigint
 * - document_id: bigint
 * - sale_note_id: bigint
 * - technical_service_id: bigint
 * - expense_payment_id: bigint
 * - purchase_id: bigint
 * - bank_loan_payment_id: bigint
 * - quotation_id: bigint
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_documents', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('cash_id');
            $table->bigInteger('document_id')->nullable();
            $table->bigInteger('sale_note_id')->nullable();
            $table->bigInteger('technical_service_id')->nullable();
            $table->bigInteger('expense_payment_id')->nullable();
            $table->bigInteger('purchase_id')->nullable();
            $table->bigInteger('bank_loan_payment_id')->nullable();
            $table->bigInteger('quotation_id')->nullable();
            $table->index(['cash_id'], 'cash_documents_cash_id_foreign');
            $table->index(['document_id'], 'cash_documents_document_id_foreign');
            $table->index(['sale_note_id'], 'cash_documents_sale_note_id_foreign');
            $table->index(['expense_payment_id'], 'cash_documents_expense_payment_id_foreign');
            $table->index(['technical_service_id'], 'cash_documents_technical_service_id_foreign');
            $table->index(['purchase_id'], 'cash_documents_purchase_id_foreign');
            $table->index(['quotation_id'], 'cash_documents_quotation_id_foreign');
        });
        DB::statement('ALTER TABLE "cash_documents" ADD CONSTRAINT "cash_documents_bank_loan_payment_id_range" CHECK ("bank_loan_payment_id" >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_documents');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
