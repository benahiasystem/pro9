<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class TenantAddDispatchIdToInventoriesTransfer extends Migration
{
    public function up()
    {
        Schema::table('inventories_transfer', function (Blueprint $table) {
            $table->unsignedInteger('dispatch_id')->nullable()->after('description');
            $table->foreign('dispatch_id')->references('id')->on('dispatches')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::table('inventories_transfer', function (Blueprint $table) {
            $table->dropForeign(['dispatch_id']);
            $table->dropColumn('dispatch_id');
        });
    }
}
