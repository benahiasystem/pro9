<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// ######## INICIO NUMERACIÓN FISCAL VENEZUELA ########
return new class extends Migration {
    public function up(): void
    {
        Schema::create('fiscal_profiles', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->increments('id');
            $table->unsignedInteger('establishment_id')->index();
            $table->string('name', 120);
            $table->string('channel', 24);
            $table->string('document_type_id', 255)->index();
            $table->unsignedInteger('sequence_id')->index();
            $table->unsignedInteger('control_lot_id')->nullable()->index();
            $table->unsignedInteger('device_group_id')->nullable()->index();
            $table->string('mode', 24);
            $table->string('provider', 80)->default('none');
            $table->json('configuration');
            $table->text('credentials')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->index(['establishment_id', 'channel', 'document_type_id'], 'fiscal_profiles_resolution_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fiscal_profiles');
    }
};
// ######## FIN NUMERACIÓN FISCAL VENEZUELA ########
