<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('orders')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'order_code')) {
                $table->string('order_code', 20)->nullable()->after('id');
                $table->unique('order_code');
            }
        });
    }

    public function down()
    {
        if (! Schema::hasTable('orders')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'order_code')) {
                $table->dropUnique(['order_code']);
                $table->dropColumn('order_code');
            }
        });
    }
};
