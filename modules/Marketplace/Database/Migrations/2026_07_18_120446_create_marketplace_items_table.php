<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::connection('system')->create('marketplace_items', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('store_id');

            // Id del ítem en la app. Llave natural junto a store_id.
            $table->string('external_id', 64);

            $table->string('name', 200);
            $table->string('name_normalized', 200)->index();
            $table->string('internal_code', 64)->nullable()->index();
            $table->string('barcode', 64)->nullable()->index();

            $table->unsignedBigInteger('category_id')->nullable();

            // Texto crudo que envió la app, para trazabilidad.
            $table->string('source_category', 120)->nullable();

            $table->string('image_path')->nullable();
            $table->char('image_hash', 64)->nullable();

            // 'blocked' solo lo pone y lo quita el admin: sobrevive a cualquier sync.
            $table->enum('status', ['active', 'inactive', 'blocked'])
                ->default('active')
                ->index();

            $table->timestamp('blocked_at')->nullable();
            $table->string('blocked_reason', 255)->nullable();

            $table->unsignedInteger('reports_count')->default(0);
            $table->timestamp('last_synced_at')->nullable();

            $table->timestamps();

            $table->unique(['store_id', 'external_id']);
            $table->index(['store_id', 'status']);
            $table->index(['category_id', 'status']);

            $table->foreign('store_id')
                ->references('id')->on('marketplace_stores')
                ->onDelete('cascade');

            $table->foreign('category_id')
                ->references('id')->on('marketplace_categories')
                ->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::connection('system')->dropIfExists('marketplace_items');
    }
};
