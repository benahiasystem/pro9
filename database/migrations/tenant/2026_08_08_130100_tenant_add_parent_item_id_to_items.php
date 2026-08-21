<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('items', function (Blueprint $table) {
            // items.id es increments (unsigned int), la FK debe coincidir
            $table->unsignedInteger('parent_item_id')->nullable()->index()->after('is_set');

            $table->foreign('parent_item_id')
                ->references('id')
                ->on('items')
                ->onDelete('restrict');
        });
    }

    public function down()
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropForeign(['parent_item_id']);
            $table->dropIndex(['parent_item_id']);
            $table->dropColumn('parent_item_id');
        });
    }
};
