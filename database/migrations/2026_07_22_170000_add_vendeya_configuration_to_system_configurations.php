<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddVendeyaConfigurationToSystemConfigurations extends Migration
{
    public function up()
    {
        Schema::connection('system')->table('configurations', function (Blueprint $table) {
            $table->json('vendeya_configuration')->nullable();
        });

        $defaults = [
            'brandName' => 'Vendeya.pe',
            'Primary' => '#ff7d00',
            'Secondary' => '#d5e8e8',
            'Accent' => '#ffffff',
            'Background' => '#eef5f5',
            'White' => '#ffffff',
            'Text' => '#004850',
            'lightText' => '#a2a5b9',
            'darkPrimary' => '#121c22',
            'darkSecondary' => '#1c2a32',
            'darkAccent' => '#253945',
            'darkBackground' => '#1b262c',
            'darkLightText' => '#a9a9b2',
            'useSystemLogo' => true,
            'logoVersion' => null,
        ];

        DB::connection('system')->table('configurations')->update([
            'vendeya_configuration' => json_encode($defaults, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }

    public function down()
    {
        Schema::connection('system')->table('configurations', function (Blueprint $table) {
            $table->dropColumn('vendeya_configuration');
        });
    }
}
