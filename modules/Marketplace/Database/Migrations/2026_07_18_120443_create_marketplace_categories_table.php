<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Taxonomía plana, un solo nivel. Se construye sola con lo que envían
        // las apps: el slug es la llave natural y el admin no crea nada.
        Schema::connection('system')->create('marketplace_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('slug', 140)->unique();
            $table->unsignedInteger('items_count')->default(0);
            $table->boolean('is_visible')->default(true);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::connection('system')->dropIfExists('marketplace_categories');
    }
};
