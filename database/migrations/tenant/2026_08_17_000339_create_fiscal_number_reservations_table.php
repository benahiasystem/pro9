<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// ######## INICIO NUMERACIÓN FISCAL VENEZUELA ########
return new class extends Migration {
    public function up(): void
    {
        Schema::create('fiscal_number_reservations', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->increments('id');
            $table->string('operation_key', 128)->unique();
            $table->char('payload_fingerprint', 64);
            $table->unsignedInteger('sequence_id')->index();
            $table->unsignedInteger('profile_id')->nullable()->index();
            $table->unsignedBigInteger('document_number');
            $table->unsignedInteger('control_lot_id')->nullable()->index();
            $table->string('control_number', 11)->nullable()->unique();
            $table->json('fiscal_snapshot');
            $table->string('status', 24)->default('reserved');
            $table->unsignedInteger('document_id')->nullable()->unique();
            $table->unsignedInteger('dispatch_id')->nullable()->unique();
            // A physical contingency replaces an emission, not its commercial transaction.
            $table->unsignedInteger('parent_reservation_id')->nullable()->unique();
            $table->unsignedInteger('current_attempt_id')->nullable()->index();
            $table->json('provider_result')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->unsignedInteger('confirmed_by')->nullable()->index();
            $table->string('invalidation_reason', 255)->nullable();
            $table->timestamps();
            $table->unique(['sequence_id', 'document_number'], 'fiscal_reservations_document_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fiscal_number_reservations');
    }
};
// ######## FIN NUMERACIÓN FISCAL VENEZUELA ########
