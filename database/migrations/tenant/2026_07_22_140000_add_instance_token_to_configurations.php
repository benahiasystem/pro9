<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddInstanceTokenToConfigurations extends Migration
{
    public function up()
    {
        Schema::table('configurations', function (Blueprint $table) {
            $table->string('evolution_instance_token')->nullable()->after('evolution_profile_name');
            $table->string('qr_api_instance_token')->nullable()->after('qr_api_profile_name');
        });
    }

    public function down()
    {
        Schema::table('configurations', function (Blueprint $table) {
            $table->dropColumn(['evolution_instance_token', 'qr_api_instance_token']);
        });
    }
}
