<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// ######## INICIO NUMERACIÓN FISCAL VENEZUELA ########
return new class extends Migration {
    public function up(): void
    {
        Schema::create('fiscal_control_lots', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->increments('id');
            $table->unsignedInteger('establishment_id')->index();
            $table->string('printer_name');
            $table->string('printer_rif', 32);
            $table->string('authorization');
            $table->date('authorization_date');
            $table->date('prepared_at');
            $table->unsignedBigInteger('start_ordinal');
            $table->unsignedBigInteger('end_ordinal');
            $table->unsignedBigInteger('next_ordinal');
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->index(['start_ordinal', 'end_ordinal'], 'fiscal_control_lots_range_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fiscal_control_lots');
    }
};
// ######## FIN NUMERACIÓN FISCAL VENEZUELA ########
