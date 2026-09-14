<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// ######## INICIO NUMERACIÓN FISCAL VENEZUELA ########
return new class extends Migration {
    public function up(): void
    {
        Schema::create('fiscal_demo_receipts', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->increments('id');
            $table->string('operation_key', 128)->unique();
            $table->char('payload_fingerprint', 64);
            $table->json('receipt');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fiscal_demo_receipts');
    }
};
// ######## FIN NUMERACIÓN FISCAL VENEZUELA ########
