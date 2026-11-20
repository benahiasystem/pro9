<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddDataModuleForEstablishmentsUsers extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::table('modules')->insert([ 
            ['id'=> 14, 'value' => 'establishments', 'description' => 'Usuarios/Locales & Series']
        ]);
        \App\Database\SequenceSynchronizer::sync(\Illuminate\Support\Facades\DB::connection());
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::table('modules')->where('id', 14)->delete();
    }
}
