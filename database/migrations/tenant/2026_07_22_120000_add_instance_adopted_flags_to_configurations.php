<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddInstanceAdoptedFlagsToConfigurations extends Migration
{
    public function up()
    {
        Schema::table('configurations', function (Blueprint $table) {
            $table->boolean('evolution_instance_adopted')->default(false)->after('evolution_instance');
            $table->boolean('qr_api_instance_adopted')->default(false)->after('qr_api_instance');
        });
    }

    public function down()
    {
        Schema::table('configurations', function (Blueprint $table) {
            $table->dropColumn(['evolution_instance_adopted', 'qr_api_instance_adopted']);
        });
    }
}
