<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('extra_services')) {
            DB::table('extra_services')->updateOrInsert(
                ['service' => 'apidocs'],
                [
                    'is_active' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (Schema::hasTable('extra_services')) {
            DB::table('extra_services')->where('service', 'apidocs')->delete();
        }
    }
};
