<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// New-install consolidation: create this table before the final tenant keys.
return new class extends Migration {
    public function up(): void
    {
        if (Schema::connection('tenant')->hasTable('sellnow_sales')) return;
        Schema::connection('tenant')->create('sellnow_sales', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('client_sale_id')->unique();
            $table->uuid('device_id');
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('establishment_id');
            $table->string('kind', 20);
            $table->char('payload_hash', 64);
            $table->unsignedInteger('original_cash_id');
            $table->unsignedInteger('cash_id');
            $table->dateTime('recorded_at');
            $table->string('status', 24)->default('pending');
            $table->unsignedInteger('document_id')->nullable();
            $table->unsignedInteger('sale_note_id')->nullable();
            $table->longText('envelope');
            $table->longText('result')->nullable();
            $table->longText('error')->nullable();
            $table->longText('effects')->nullable();
            $table->longText('cash_history')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('sellnow_sales');
    }
};
