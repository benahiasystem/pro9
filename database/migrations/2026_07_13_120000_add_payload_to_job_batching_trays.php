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
        Schema::table('job_batching_trays', function (Blueprint $table) {
            $table->json('payload')->nullable()->after('generated_filename');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('job_batching_trays', function (Blueprint $table) {
            $table->dropColumn('payload');
        });
    }
};
