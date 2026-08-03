<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Si la tienda muestra o no los precios de su catálogo.
     *
     * Opt-in (default false): el marketplace nació sin precios —«se coordina por
     * WhatsApp»— y una tienda debe habilitarlo a propósito. Con esto en false,
     * el precio de sus ítems no se expone aunque venga en el sync.
     */
    public function up()
    {
        Schema::connection('system')->table('marketplace_stores', function (Blueprint $table) {
            $table->boolean('show_prices')->default(false)->after('address');
        });
    }

    public function down()
    {
        Schema::connection('system')->table('marketplace_stores', function (Blueprint $table) {
            $table->dropColumn('show_prices');
        });
    }
};
