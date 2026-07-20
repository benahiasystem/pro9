<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddMozoConfigurationToSystemConfigurations extends Migration
{
    public function up()
    {
        Schema::connection('system')->table('configurations', function (Blueprint $table) {
            $table->json('mozo_configuration')->nullable();
        });

        $defaults = [
            'brandName' => 'Mozo.pe',
            'Primary' => '#32a56a',
            'Secondary' => '#f58f00',
            'Accent' => '#115733',
            'Background' => '#f4f5f6',
            'White' => '#ffffff',
            'Text' => '#1d3a3a',
            'lightText' => '#a2a5b9',
            'darkPrimary' => '#222225',
            'darkSecondary' => '#27272a',
            'darkAccent' => '#313135',
            'darkBackground' => '#3b3b40',
            'darkLightText' => '#d0d2dc',
        ];

        $buildConfigurationPath = public_path('mozo/config.json');
        if (is_file($buildConfigurationPath) && is_readable($buildConfigurationPath)) {
            $contents = file_get_contents($buildConfigurationPath);
            $buildConfiguration = $contents === false ? null : json_decode($contents, true);

            if (is_array($buildConfiguration)) {
                $defaults = array_replace(
                    $defaults,
                    array_intersect_key($buildConfiguration, $defaults)
                );
            }
        }

        DB::connection('system')->table('configurations')->update([
            'mozo_configuration' => json_encode($defaults, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }

    public function down()
    {
        Schema::connection('system')->table('configurations', function (Blueprint $table) {
            $table->dropColumn('mozo_configuration');
        });
    }
}
