<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - sale_note_id: bigint
 * - date: date
 * - currency_type_id: varchar(255)
 * - amount: numeric(30,12)
 * - payment_method_type_id: char(2)
 * - calculation_snapshot: json
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_note_fees', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('sale_note_id');
            $table->date('date');
            $table->string('currency_type_id', 255)->collation('pro9_ci_ai');
            $table->decimal('amount', 30, 12);
            $table->char('payment_method_type_id', 2)->collation('pro9_ci_ai')->nullable()->comment('Relacion con el metodo de pago, Nulo es pago a cuotas');
            $table->json('calculation_snapshot')->nullable()->comment('Exact decimal fractions v1');
            $table->index(['sale_note_id'], 'sale_note_fees_sale_note_id_foreign');
            $table->index(['currency_type_id'], 'sale_note_fees_currency_type_id_foreign');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_note_fees');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
