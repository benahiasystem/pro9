<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Máquinas VendeYa enroladas. El token viaja una sola vez al enrolar;
 * aquí solo se guarda su hash.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::create('offline_machines', function (Blueprint $table) {
            $table->increments('id');
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('token_hash', 64)->index();
            $table->unsignedInteger('user_id')->nullable()->comment('Admin que enroló');
            $table->unsignedInteger('establishment_id');
            $table->unsignedInteger('series_device_group_id')->nullable();
            $table->string('status', 20)->default('active')->comment('active|revoked');
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

        });
    }

    public function down()
    {
        Schema::dropIfExists('offline_machines');
    }
};
