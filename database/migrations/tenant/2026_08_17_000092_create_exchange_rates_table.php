<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - date: date
 * - sale_original: numeric(18,8)
 * - purchase_original: numeric(18,8)
 * - purchase: numeric(18,8)
 * - sale: numeric(18,8)
 * - date_original: date
 * - created_at: timestamp
 * - updated_at: timestamp
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->date('date');
            $table->decimal('sale_original', 18, 8);
            $table->decimal('purchase_original', 18, 8);
            $table->decimal('purchase', 18, 8);
            $table->decimal('sale', 18, 8);
            $table->date('date_original');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->primary(['date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exchange_rates');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
