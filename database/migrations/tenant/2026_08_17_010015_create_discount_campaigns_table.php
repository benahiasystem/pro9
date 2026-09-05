<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (Schema::hasTable('discount_campaigns')) {
            return;
        }

        Schema::create('discount_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('discount_type')->default('percentage');
            $table->decimal('value', 12, 2);
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'starts_at', 'expires_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('discount_campaigns');
    }
};
