<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - date_time_start: timestamp
 * - renew: boolean
 * - quantity_documents: integer
 * - created_at: timestamp
 * - updated_at: timestamp
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_cycles', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->timestamp('date_time_start');
            $table->boolean('renew')->default(false);
            $table->integer('quantity_documents');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
        DB::statement('ALTER TABLE "billing_cycles" ADD CONSTRAINT "billing_cycles_quantity_documents_range" CHECK ("quantity_documents" BETWEEN -2147483648 AND 2147483647)');
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_cycles');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
