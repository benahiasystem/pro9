<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TenantAddEnableGlobalDiscountToConfigurations extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasColumn('configurations', 'enable_global_discount')) {
            Schema::table('configurations', function (Blueprint $table) {
                $table->boolean('enable_global_discount')->default(false);
            });
        }

        DB::table('configurations')->update([
            'enable_global_discount' => (bool) config('tenant.enabled_discount_global'),
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (Schema::hasColumn('configurations', 'enable_global_discount')) {
            Schema::table('configurations', function (Blueprint $table) {
                $table->dropColumn('enable_global_discount');
            });
        }
    }
}
