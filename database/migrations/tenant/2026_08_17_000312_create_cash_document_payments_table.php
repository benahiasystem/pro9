<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - cash_id: bigint
 * - document_payment_id: bigint
 * - sale_note_payment_id: bigint
 * - cash_document_id: bigint
 * - cash_document_credit_id: bigint
 * - created_at: timestamp
 * - updated_at: timestamp
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_document_payments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('cash_id')->nullable();
            $table->bigInteger('document_payment_id')->nullable();
            $table->bigInteger('sale_note_payment_id')->nullable();
            $table->bigInteger('cash_document_id')->nullable();
            $table->bigInteger('cash_document_credit_id')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index(['cash_id'], 'cash_document_payments_cash_id_foreign');
            $table->index(['document_payment_id'], 'cash_document_payments_document_payment_id_foreign');
            $table->index(['sale_note_payment_id'], 'cash_document_payments_sale_note_payment_id_foreign');
            $table->index(['cash_document_id'], 'cash_document_payments_cash_document_id_foreign');
            $table->index(['cash_document_credit_id'], 'cash_document_payments_cash_document_credit_id_foreign');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_document_payments');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
