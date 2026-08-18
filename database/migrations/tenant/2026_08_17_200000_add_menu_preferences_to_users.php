<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddMenuPreferencesToUsers extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('pinned_items')->nullable()->after('from_guest_register');
            $table->json('menu_order')->nullable()->after('pinned_items');
            $table->boolean('show_only_active_menu')->default(false)->after('menu_order');
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['pinned_items', 'menu_order', 'show_only_active_menu']);
        });
    }
}
