<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (Schema::hasTable('discount_campaigns') && ! Schema::hasColumn('discount_campaigns', 'discount_type')) {
            Schema::table('discount_campaigns', function (Blueprint $table) {
                $table->string('discount_type')->default('percentage')->after('name');
            });

            if (Schema::hasColumn('discount_campaigns', 'type')) {
                DB::table('discount_campaigns')->update(['discount_type' => 'percentage']);
                Schema::table('discount_campaigns', fn (Blueprint $table) => $table->dropColumn('type'));
            }
        }

        if (! Schema::hasTable('campaign_product')) {
            Schema::create('campaign_product', function (Blueprint $table) {
                $table->unsignedBigInteger('discount_campaign_id');
                $table->unsignedInteger('item_id');
                $table->primary(['discount_campaign_id', 'item_id']);
                $table->foreign('discount_campaign_id')->references('id')->on('discount_campaigns')->cascadeOnDelete();
                $table->foreign('item_id')->references('id')->on('items')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('campaign_category')) {
            Schema::create('campaign_category', function (Blueprint $table) {
                $table->unsignedBigInteger('discount_campaign_id');
                $table->unsignedInteger('category_id');
                $table->primary(['discount_campaign_id', 'category_id']);
                $table->foreign('discount_campaign_id')->references('id')->on('discount_campaigns')->cascadeOnDelete();
                $table->foreign('category_id')->references('id')->on('categories')->cascadeOnDelete();
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('campaign_category');
        Schema::dropIfExists('campaign_product');
    }
};
