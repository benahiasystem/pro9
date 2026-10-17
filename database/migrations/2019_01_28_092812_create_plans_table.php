<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePlansTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('plans', function (Blueprint $table) {
            // ######## INICIO CALCULOS EXACTOS PRO9 ########
            $table->json('calculation_snapshot')->nullable();
            // ######## FIN CALCULOS EXACTOS PRO9 ########
            $table->increments('id');
            $table->string('name');
            $table->decimal('pricing', 30, 12);
            $table->bigInteger('limit_users');
            $table->bigInteger('limit_documents');
            $table->json('plan_documents'); 
            $table->boolean('locked')->default(false);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('plans');
    }
}
