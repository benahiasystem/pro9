<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateWahaServersTable extends Migration
{
    public function up()
    {
        Schema::create('waha_servers', function (Blueprint $table) {
            $table->id();
            $table->string('key', 60)->unique();
            $table->string('name', 120);
            $table->string('url', 255);
            $table->string('api_key', 255);
            $table->string('engine', 20);
            $table->boolean('is_default')->default(false);
            $table->boolean('active')->default(true);
            $table->string('notes', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('waha_servers');
    }
}
