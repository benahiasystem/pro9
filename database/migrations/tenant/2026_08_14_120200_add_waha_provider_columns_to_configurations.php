<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddWahaProviderColumnsToConfigurations extends Migration
{
    public function up()
    {
        Schema::table('configurations', function (Blueprint $table) {
            $table->string('evolution_provider', 20)->default('evolution')->after('evolution_instance_adopted');
            $table->string('evolution_waha_server_key', 60)->nullable()->after('evolution_provider');
            $table->string('qr_api_provider', 20)->default('evolution')->after('qr_api_instance_adopted');
            $table->string('qr_api_waha_server_key', 60)->nullable()->after('qr_api_provider');
        });
    }

    public function down()
    {
        Schema::table('configurations', function (Blueprint $table) {
            $table->dropColumn(['evolution_provider', 'evolution_waha_server_key', 'qr_api_provider', 'qr_api_waha_server_key']);
        });
    }
}
