<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - filename: text
 * - purchase_id: bigint
 * - document_id: bigint
 * - order_note_id: bigint
 * - quotation_id: bigint
 * - sale_note_id: bigint
 * - created_at: timestamp
 * - updated_at: timestamp
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guide_files', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->text('filename')->collation('sab_ci_ai')->nullable()->comment('Nombre de archivo');
            $table->bigInteger('purchase_id')->nullable()->default('0')->comment('Relacion con purchases');
            $table->bigInteger('document_id')->nullable()->default('0')->comment('Relacion con documents');
            $table->bigInteger('order_note_id')->nullable()->default('0')->comment('Relacion con order_notes');
            $table->bigInteger('quotation_id')->nullable()->default('0')->comment('Relacion con quotations');
            $table->bigInteger('sale_note_id')->nullable()->default('0')->comment('Relacion con sale_notes');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
        DB::statement('ALTER TABLE "guide_files" ADD CONSTRAINT "guide_files_purchase_id_range" CHECK ("purchase_id" >= 0)');
        DB::statement('ALTER TABLE "guide_files" ADD CONSTRAINT "guide_files_document_id_range" CHECK ("document_id" >= 0)');
        DB::statement('ALTER TABLE "guide_files" ADD CONSTRAINT "guide_files_order_note_id_range" CHECK ("order_note_id" >= 0)');
        DB::statement('ALTER TABLE "guide_files" ADD CONSTRAINT "guide_files_quotation_id_range" CHECK ("quotation_id" >= 0)');
        DB::statement('ALTER TABLE "guide_files" ADD CONSTRAINT "guide_files_sale_note_id_range" CHECK ("sale_note_id" >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('guide_files');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
