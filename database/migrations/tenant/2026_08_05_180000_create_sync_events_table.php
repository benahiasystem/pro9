<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bitácora de eventos recibidos de las máquinas VendeYa (lotes cronológicos).
 * Es la fuente de idempotencia del canal y la bandeja de errores/pendientes
 * del lado del facturador.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::create('sync_events', function (Blueprint $table) {
            $table->id();
            // offline_machines/documents/cashes usan increments (INT) — legacy del repo
            $table->unsignedInteger('machine_id');
            $table->unsignedInteger('seq');
            $table->string('type', 20);
            $table->uuid('external_id')->unique();
            $table->dateTime('occurred_at');
            $table->longText('payload');
            $table->string('hash')->nullable();
            $table->string('status', 20)->default('accepted');
            $table->text('message')->nullable();
            $table->unsignedInteger('document_id')->nullable();
            $table->unsignedInteger('cash_id')->nullable();
            $table->timestamps();

            $table->index(['machine_id', 'seq']);
            $table->index('status');
            $table->foreign('machine_id')->references('id')->on('offline_machines');
        });
    }

    public function down()
    {
        Schema::dropIfExists('sync_events');
    }
};
