<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// ######## INICIO NUMERACIÓN FISCAL VENEZUELA ########
return new class extends Migration {
    public function up(): void
    {
        Schema::create('fiscal_numbering_audits', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->increments('id');
            $table->unsignedInteger('actor_id')->index();
            $table->string('action', 64);
            $table->string('entity_type', 32);
            $table->unsignedInteger('entity_id');
            $table->json('changed_fields');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fiscal_numbering_audits');
    }
};
// ######## FIN NUMERACIÓN FISCAL VENEZUELA ########
