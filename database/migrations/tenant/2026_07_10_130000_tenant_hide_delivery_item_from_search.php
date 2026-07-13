<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    
    public function up()
    {
        DB::table('items')
            ->where('internal_id', 'DELIVERY-ECOM')
            ->update(['hidden_search' => 1]);
    }

    /**
     * Revierte la marca, dejando el ítem visible en los buscadores.
     */
    public function down()
    {
        DB::table('items')
            ->where('internal_id', 'DELIVERY-ECOM')
            ->update(['hidden_search' => 0]);
    }
};
