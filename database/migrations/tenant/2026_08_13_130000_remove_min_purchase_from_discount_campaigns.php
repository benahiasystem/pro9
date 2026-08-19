<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (Schema::hasTable('discount_campaigns') && Schema::hasColumn('discount_campaigns', 'min_purchase')) {
            Schema::table('discount_campaigns', function (Blueprint $table) {
                $table->dropColumn('min_purchase');
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('discount_campaigns') && ! Schema::hasColumn('discount_campaigns', 'min_purchase')) {
            Schema::table('discount_campaigns', function (Blueprint $table) {
                $table->decimal('min_purchase', 12, 2)->nullable()->after('value');
            });
        }
    }
};
