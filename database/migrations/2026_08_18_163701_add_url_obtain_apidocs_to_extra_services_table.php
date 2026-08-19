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
        DB::table('extra_services')
            ->where('id', 1)
            ->update([
                'urlObtainApidocs' => 'https://buho.la/cart.php?a=add&pid=181&billingcycle=annually&promocode=APIGRATIS&skipconfig=1'
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('extra_services')
            ->where('name', 'apidocs')
            ->update([
                'urlObtainApidocs' => 'https://buho.la/'
            ]);
    }
};