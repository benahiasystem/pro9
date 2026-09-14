<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// ######## INICIO NUMERACIÓN FISCAL VENEZUELA ########
return new class extends Migration {
    public function up(): void
    {
        Schema::create('fiscal_sequences', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->increments('id');
            $table->unsignedInteger('establishment_id')->nullable()->index();
            $table->string('document_type_id', 255)->index();
            $table->string('series_code', 32)->default('');
            $table->unsignedBigInteger('initial_number');
            $table->unsignedBigInteger('next_number');
            $table->boolean('in_use')->default(false);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['document_type_id', 'series_code'], 'fiscal_sequences_type_series_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fiscal_sequences');
    }
};
// ######## FIN NUMERACIÓN FISCAL VENEZUELA ########
