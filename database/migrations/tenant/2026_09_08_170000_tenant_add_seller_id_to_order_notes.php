<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class TenantAddSellerIdToOrderNotes extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('order_notes') || Schema::hasColumn('order_notes', 'seller_id')) {
            return;
        }

        Schema::table('order_notes', function (Blueprint $table) {
            $table->unsignedInteger('seller_id')->nullable()->after('user_id');
            $table->foreign('seller_id')->references('id')->on('users');
        });
    }

    public function down()
    {
        if (!Schema::hasTable('order_notes') || !Schema::hasColumn('order_notes', 'seller_id')) {
            return;
        }

        Schema::table('order_notes', function (Blueprint $table) {
            $table->dropForeign(['seller_id']);
            $table->dropColumn('seller_id');
        });
    }
}
