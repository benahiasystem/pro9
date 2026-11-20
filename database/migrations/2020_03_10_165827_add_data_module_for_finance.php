<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AddDataModuleForFinance extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::table('modules')->insert([ 
            ['id'=> 12, 'value' => 'finance', 'description' => 'Finanzas']
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
        DB::table('modules')->where('id', 12)->delete();
    }
}
