<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::connection('system')->create('marketplace_stores', function (Blueprint $table) {
            $table->id();

            // Identidad enviada por la app. Recomendado: el uuid del tenant.
            $table->char('external_uuid', 36)->unique();

            $table->string('name', 160);
            $table->string('name_normalized', 160)->index();

            // Se genera una sola vez y nunca cambia: los links compartidos no se rompen.
            $table->string('slug', 180)->unique();

            $table->string('tax_id', 20)->nullable();
            $table->string('email', 160)->nullable();

            // E.164 sin '+', ej. 51987654321
            $table->string('whatsapp', 20);

            $table->string('logo_path')->nullable();
            $table->char('logo_hash', 64)->nullable();
            $table->string('description', 500)->nullable();
            $table->string('address', 255)->nullable();

            $table->enum('status', ['pending', 'approved', 'rejected', 'disabled'])
                ->default('pending')
                ->index();

            // Lo escribe el admin, lo lee la tienda vía GET /status.
            $table->string('status_reason', 500)->nullable();

            $table->unsignedInteger('items_count')->default(0);
            $table->unsignedInteger('reports_count')->default(0);

            // Aceptación implícita de T&C en el primer sync.
            $table->timestamp('terms_accepted_at')->nullable();

            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('disabled_at')->nullable();

            $table->timestamp('last_synced_at')->nullable();
            $table->unsignedInteger('last_sync_items')->default(0);
            $table->string('last_sync_ip', 45)->nullable();
            $table->string('app_version', 20)->nullable();

            $table->timestamps();

            $table->index(['status', 'name']);
        });
    }

    public function down()
    {
        Schema::connection('system')->dropIfExists('marketplace_stores');
    }
};
