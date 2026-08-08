<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('item_variation_values', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('item_id');
            $table->unsignedBigInteger('product_variable_id');
            $table->unsignedBigInteger('product_variable_value_id');
            $table->timestamps();

            $table->foreign('item_id', 'ivv_item_fk')
                ->references('id')
                ->on('items')
                ->onDelete('cascade');

            $table->foreign('product_variable_id', 'ivv_variable_fk')
                ->references('id')
                ->on('product_variables')
                ->onDelete('restrict');

            $table->foreign('product_variable_value_id', 'ivv_value_fk')
                ->references('id')
                ->on('product_variable_values')
                ->onDelete('restrict');

            $table->unique(['item_id', 'product_variable_id'], 'ivv_item_variable_unique');
        });
    }

    public function down()
    {
        Schema::dropIfExists('item_variation_values');
    }
};
