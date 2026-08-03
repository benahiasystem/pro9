<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Precio y descripción del producto.
     *
     * El precio solo se muestra si la tienda tiene show_prices activo (se guarda
     * igual, el gate es de presentación). La descripción enriquece el modal del
     * producto; se guarda tal cual llega de la app.
     */
    public function up()
    {
        Schema::connection('system')->table('marketplace_items', function (Blueprint $table) {
            $table->decimal('price', 10, 2)->nullable()->after('source_category');
            $table->string('description', 1000)->nullable()->after('price');
        });
    }

    public function down()
    {
        Schema::connection('system')->table('marketplace_items', function (Blueprint $table) {
            $table->dropColumn(['price', 'description']);
        });
    }
};
