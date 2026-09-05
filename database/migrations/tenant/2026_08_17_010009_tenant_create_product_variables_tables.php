<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // ########### INICIO: permitir reordenar la migración sin duplicar tablas existentes ###########
        if (! Schema::hasTable('product_variables')) {
            Schema::create('product_variables', function (Blueprint $table) {
                $table->id();
                $table->string('name', 100)->unique();
                $table->string('value_type', 10)->default('list'); // 'list' | 'color'
                $table->boolean('active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('product_variable_values')) {
            Schema::create('product_variable_values', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('product_variable_id');
                $table->string('value', 100);
                $table->string('color', 7)->nullable();
                $table->integer('position')->default(0);
                $table->boolean('active')->default(true);
                $table->timestamps();

                $table->foreign('product_variable_id', 'pvv_variable_fk')
                    ->references('id')
                    ->on('product_variables')
                    ->onDelete('cascade');

                $table->unique(['product_variable_id', 'value'], 'pvv_variable_value_unique');
            });
        }
        // ########### FIN: permitir reordenar la migración sin duplicar tablas existentes ###########
    }

    public function down()
    {
        Schema::dropIfExists('product_variable_values');
        Schema::dropIfExists('product_variables');
    }
};
