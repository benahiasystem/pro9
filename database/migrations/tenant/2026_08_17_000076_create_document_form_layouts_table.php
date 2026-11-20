<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - variant: varchar(32)
 * - pinned_fields: json
 * - updated_by_user_id: numeric(20,0)
 * - created_at: timestamp
 * - updated_at: timestamp
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_form_layouts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('variant', 32)->collation('pro9_ci_ai');
            $table->json('pinned_fields')->nullable();
            $table->decimal('updated_by_user_id', 20, 0)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['variant'], 'document_form_layouts_variant_unique');
        });
        DB::statement('ALTER TABLE "document_form_layouts" ADD CONSTRAINT "document_form_layouts_updated_by_user_id_range" CHECK ("updated_by_user_id" BETWEEN 0 AND 18446744073709551615)');
    }

    public function down(): void
    {
        Schema::dropIfExists('document_form_layouts');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
