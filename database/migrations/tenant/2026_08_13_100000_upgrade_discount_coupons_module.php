<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('discount_coupons')) {
            return;
        }

        Schema::table('discount_coupons', function (Blueprint $table) {
            if (! Schema::hasColumn('discount_coupons', 'uses_count')) {
                $table->unsignedInteger('uses_count')->default(0)->after('max_total_uses');
            }

            if (! Schema::hasColumn('discount_coupons', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        if (Schema::hasColumn('discount_coupons', 'expires_at') && DB::connection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE discount_coupons MODIFY expires_at DATETIME NULL');
        }

        if (Schema::hasTable('discount_coupon_usages')) {
            DB::table('discount_coupons')->update([
                'uses_count' => DB::raw('(SELECT COUNT(*) FROM discount_coupon_usages WHERE discount_coupon_usages.discount_coupon_id = discount_coupons.id)'),
            ]);
        }
    }

    public function down()
    {
        if (! Schema::hasTable('discount_coupons')) {
            return;
        }

        Schema::table('discount_coupons', function (Blueprint $table) {
            if (Schema::hasColumn('discount_coupons', 'uses_count')) {
                $table->dropColumn('uses_count');
            }

            if (Schema::hasColumn('discount_coupons', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
    }
};
