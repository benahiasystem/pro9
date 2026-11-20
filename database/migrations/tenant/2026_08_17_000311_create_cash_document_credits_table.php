<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - cash_id: bigint
 * - cash_id_processed: bigint
 * - document_id: bigint
 * - sale_note_id: bigint
 * - status: varchar(15)
 * - created_at: timestamp
 * - updated_at: timestamp
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_document_credits', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('cash_id');
            $table->bigInteger('cash_id_processed')->nullable();
            $table->bigInteger('document_id')->nullable();
            $table->bigInteger('sale_note_id')->nullable();
            $table->string('status', 15)->collation('sab_ci_ai')->default('PENDING');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index(['document_id'], 'cash_document_credits_document_id_foreign');
            $table->index(['sale_note_id'], 'cash_document_credits_sale_note_id_foreign');
            $table->index(['cash_id'], 'cash_document_credits_cash_id_foreign');
        });
        DB::statement('ALTER TABLE "cash_document_credits" ADD CONSTRAINT "cash_document_credits_cash_id_processed_range" CHECK ("cash_id_processed" BETWEEN 0 AND 4294967295)');
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_document_credits');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
