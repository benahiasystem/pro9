<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Denuncias mínimas: sin updated_at, sin detalle, sin email, sin captcha.
        Schema::connection('system')->create('marketplace_reports', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('store_id')->index();
            $table->unsignedBigInteger('item_id')->nullable()->index();

            $table->string('reason', 60);
            $table->string('ip', 45)->nullable();

            $table->enum('status', ['open', 'dismissed'])->default('open')->index();

            $table->timestamp('created_at')->nullable();

            $table->foreign('store_id')
                ->references('id')->on('marketplace_stores')
                ->onDelete('cascade');

            $table->foreign('item_id')
                ->references('id')->on('marketplace_items')
                ->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::connection('system')->dropIfExists('marketplace_reports');
    }
};
