<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (Schema::hasTable('ecommerce_campaigns')) {
            return;
        }

        Schema::create('ecommerce_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('discount_type')->default('percentage');
            $table->decimal('discount_value', 12, 4)->default(0);
            $table->dateTime('start_date')->nullable();
            $table->dateTime('end_date')->nullable();
            $table->json('sp_product_ids')->nullable();
            $table->boolean('status')->default(true);

            $table->boolean('sp_countdown')->default(false);
            $table->boolean('sp_discount_price')->default(false);
            $table->boolean('sp_purchase_count')->default(false);
            $table->boolean('sp_views_count')->default(false);
            $table->boolean('sp_stock_alert')->default(false);
            $table->boolean('sp_rating')->default(false);

            $table->unsignedInteger('sp_stock_threshold')->default(10);
            $table->integer('sp_views_min')->default(10);
            $table->integer('sp_views_max')->default(50);
            $table->integer('sp_purchase_min')->default(5);
            $table->integer('sp_purchase_max')->default(30);

            $table->timestamps();

            $table->index(['status', 'end_date'], 'ecommerce_campaigns_status_end_idx');
        });
    }

    public function down()
    {
        Schema::dropIfExists('ecommerce_campaigns');
    }
};
