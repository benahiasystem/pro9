<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - name: varchar(255)
 * - slug: varchar(255)
 * - type: varchar(255)
 * - required: boolean
 * - options: json
 * - order: integer
 * - enabled_for_documents: boolean
 * - enabled_for_sale_notes: boolean
 * - enabled_for_dispatches: boolean
 * - enabled_for_order_notes: boolean
 * - enabled_for_quotations: boolean
 * - show_in_pdf: boolean
 * - created_at: timestamp
 * - updated_at: timestamp
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_fields', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 255)->collation('pro9_ci_ai');
            $table->string('slug', 255)->collation('pro9_ci_ai');
            $table->string('type', 255)->collation('pro9_ci_ai')->nullable();
            $table->boolean('required')->default(false);
            $table->json('options')->nullable();
            $table->integer('order')->default('0');
            $table->boolean('enabled_for_documents')->default(false);
            $table->boolean('enabled_for_sale_notes')->default(false);
            $table->boolean('enabled_for_dispatches')->default(false);
            $table->boolean('enabled_for_order_notes')->default(false);
            $table->boolean('enabled_for_quotations')->default(false);
            $table->boolean('show_in_pdf')->default(true);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['slug'], 'custom_fields_slug_unique');
        });
        DB::statement('ALTER TABLE "custom_fields" ADD CONSTRAINT "custom_fields_order_range" CHECK ("order" BETWEEN -2147483648 AND 2147483647)');
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_fields');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
