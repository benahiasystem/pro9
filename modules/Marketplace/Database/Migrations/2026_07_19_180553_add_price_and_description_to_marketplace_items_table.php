<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

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
        Schema::table('marketplace_items', function (Blueprint $table) {
            $table->decimal('price', 30, 12)->nullable()->after('source_category');
            $table->json('calculation_snapshot')->nullable();
            $table->string('description', 1000)->nullable()->after('price');
        });
        DB::statement('ALTER TABLE marketplace_items ADD CONSTRAINT marketplace_items_price_range CHECK (price >= 0 AND price <= 99999999.99)');
    }

    public function down()
    {
        DB::statement('ALTER TABLE marketplace_items DROP CONSTRAINT marketplace_items_price_range');
        Schema::table('marketplace_items', function (Blueprint $table) {
            $table->dropColumn(['price', 'description', 'calculation_snapshot']);
        });
    }
};
