<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        DB::table('extra_services')->update([
            'urlServiceApidocs' => 'http://apidocs.ibu.pe',
        ]);
    }

    public function down()
    {
        DB::table('extra_services')->update([
            'urlServiceApidocs' => null,
        ]);
    }
};
