<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('extra_services', function (Blueprint $table) {
            $table->string('urlApidocs')->nullable()->after('isActiveApidocs');
        });
    }

    public function down()
    {
        Schema::table('extra_services', function (Blueprint $table) {
            $table->dropColumn('urlApidocs');
        });
    }
};