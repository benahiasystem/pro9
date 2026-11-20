<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - order_number: text
 * - transaction_code: text
 * - order_note_id: bigint
 * - document_id: bigint
 * - created_at: timestamp
 * - updated_at: timestamp
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mi_tienda_pe', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->text('order_number')->collation('pro9_ci_ai')->nullable()->comment('Numero de pedido');
            $table->text('transaction_code')->collation('pro9_ci_ai')->nullable()->comment('Codigo de pasareña');
            $table->bigInteger('order_note_id')->nullable()->default('0');
            $table->bigInteger('document_id')->nullable()->default('0');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
        DB::statement('ALTER TABLE "mi_tienda_pe" ADD CONSTRAINT "mi_tienda_pe_order_note_id_range" CHECK ("order_note_id" >= 0)');
        DB::statement('ALTER TABLE "mi_tienda_pe" ADD CONSTRAINT "mi_tienda_pe_document_id_range" CHECK ("document_id" >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('mi_tienda_pe');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
