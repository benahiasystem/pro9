<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - name_printer: varchar(255)
 * - status: smallint
 * - pdf_b64: text
 * - created_at: timestamp
 * - updated_at: timestamp
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('print_orders', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name_printer', 255)->collation('pro9_ci_ai')->comment('Nombre de la impresora');
            $table->smallInteger('status')->default('0')->comment('Estado de la orden de impresión: 0 = pendiente, 1 = procesando, 2 = impresa');
            $table->text('pdf_b64')->collation('pro9_ci_ai')->comment('Archivo PDF codificado en base64');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
        DB::statement('ALTER TABLE "print_orders" ADD CONSTRAINT "print_orders_status_range" CHECK ("status" BETWEEN -128 AND 127)');
    }

    public function down(): void
    {
        Schema::dropIfExists('print_orders');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
