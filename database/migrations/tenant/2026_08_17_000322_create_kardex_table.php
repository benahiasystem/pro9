<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - date_of_issue: date
 * - type: varchar(255)
 * - item_id: bigint
 * - document_id: bigint
 * - purchase_id: bigint
 * - purchase_settlement_id: bigint
 * - sale_note_id: bigint
 * - quantity: numeric(30,12)
 * - created_at: timestamp
 * - updated_at: timestamp
 * - calculation_snapshot: json
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kardex', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->date('date_of_issue');
            $table->string('type', 255)->collation('sab_ci_ai')->nullable();
            $table->bigInteger('item_id');
            $table->bigInteger('document_id')->nullable();
            $table->bigInteger('purchase_id')->nullable();
            $table->bigInteger('purchase_settlement_id')->nullable();
            $table->bigInteger('sale_note_id')->nullable();
            $table->decimal('quantity', 30, 12);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->json('calculation_snapshot')->nullable()->comment('Exact decimal fractions v1');
            $table->index(['purchase_id'], 'kardex_purchase_id_foreign');
            $table->index(['document_id'], 'kardex_document_id_foreign');
            $table->index(['item_id'], 'kardex_item_id_foreign');
            $table->index(['sale_note_id'], 'kardex_sale_note_id_foreign');
            $table->index(['purchase_settlement_id'], 'kardex_purchase_settlement_id_foreign');
        });
        DB::statement('ALTER TABLE "kardex" ADD CONSTRAINT "kardex_type_check" CHECK ("type" IN (\'sale\',\'purchase\'))');
    }

    public function down(): void
    {
        Schema::dropIfExists('kardex');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
