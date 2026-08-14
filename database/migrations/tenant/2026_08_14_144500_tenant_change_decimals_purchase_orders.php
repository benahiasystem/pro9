<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->decimal('total_prepayment', 16, 6)->default(0)->change();
            $table->decimal('total_charge', 16, 6)->default(0)->change();
            $table->decimal('total_discount', 16, 6)->default(0)->change();
            $table->decimal('total_exportation', 16, 6)->default(0)->change();
            $table->decimal('total_free', 16, 6)->default(0)->change();
            $table->decimal('total_taxed', 16, 6)->default(0)->change();
            $table->decimal('total_unaffected', 16, 6)->default(0)->change();
            $table->decimal('total_exonerated', 16, 6)->default(0)->change();
            $table->decimal('total_igv', 16, 6)->default(0)->change();
            $table->decimal('total_base_isc', 16, 6)->default(0)->change();
            $table->decimal('total_isc', 16, 6)->default(0)->change();
            $table->decimal('total_base_other_taxes', 16, 6)->default(0)->change();
            $table->decimal('total_other_taxes', 16, 6)->default(0)->change();
            $table->decimal('total_taxes', 16, 6)->default(0)->change();
            $table->decimal('total_value', 16, 6)->default(0)->change();
            $table->decimal('total', 16, 6)->change();
        });

        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->decimal('quantity', 12, 4)->change();
            $table->decimal('total_base_igv', 16, 6)->change();
            $table->decimal('total_igv', 16, 6)->change();
            $table->decimal('total_base_isc', 16, 6)->default(0)->change();
            $table->decimal('total_isc', 16, 6)->default(0)->change();
            $table->decimal('total_base_other_taxes', 16, 6)->default(0)->change();
            $table->decimal('total_other_taxes', 16, 6)->default(0)->change();
            $table->decimal('total_taxes', 16, 6)->change();
            $table->decimal('total_value', 16, 6)->change();
            $table->decimal('total_charge', 16, 6)->default(0)->change();
            $table->decimal('total_discount', 16, 6)->default(0)->change();
            $table->decimal('total', 16, 6)->change();
        });
    }

    public function down()
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->decimal('total_prepayment', 12, 2)->default(0)->change();
            $table->decimal('total_charge', 12, 2)->default(0)->change();
            $table->decimal('total_discount', 12, 2)->default(0)->change();
            $table->decimal('total_exportation', 12, 2)->default(0)->change();
            $table->decimal('total_free', 12, 2)->default(0)->change();
            $table->decimal('total_taxed', 12, 2)->default(0)->change();
            $table->decimal('total_unaffected', 12, 2)->default(0)->change();
            $table->decimal('total_exonerated', 12, 2)->default(0)->change();
            $table->decimal('total_igv', 12, 2)->default(0)->change();
            $table->decimal('total_base_isc', 12, 2)->default(0)->change();
            $table->decimal('total_isc', 12, 2)->default(0)->change();
            $table->decimal('total_base_other_taxes', 12, 2)->default(0)->change();
            $table->decimal('total_other_taxes', 12, 2)->default(0)->change();
            $table->decimal('total_taxes', 12, 2)->default(0)->change();
            $table->decimal('total_value', 12, 2)->default(0)->change();
            $table->decimal('total', 12, 2)->change();
        });

        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->integer('quantity')->change();
            $table->decimal('total_base_igv', 12, 2)->change();
            $table->decimal('total_igv', 12, 2)->change();
            $table->decimal('total_base_isc', 12, 2)->default(0)->change();
            $table->decimal('total_isc', 12, 2)->default(0)->change();
            $table->decimal('total_base_other_taxes', 12, 2)->default(0)->change();
            $table->decimal('total_other_taxes', 12, 2)->default(0)->change();
            $table->decimal('total_taxes', 12, 2)->change();
            $table->decimal('total_value', 12, 2)->change();
            $table->decimal('total_charge', 12, 2)->default(0)->change();
            $table->decimal('total_discount', 12, 2)->default(0)->change();
            $table->decimal('total', 12, 2)->change();
        });
    }
};
