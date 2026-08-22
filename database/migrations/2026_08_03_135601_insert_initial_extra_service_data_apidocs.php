<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('extra_services')->insert([
            'urlObtainApidocs'  => 'https://buho.la/',
            'urlServiceApidocs' => 'https://api.uio.la/',
            'isActiveApidocs'   => false,
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('extra_services')
            ->where('urlObtainApidocs', 'https://buho.la/')
            ->where('urlServiceApidocs', 'https://api.uio.la/')
            ->delete();
    }
};