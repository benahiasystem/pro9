<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('payment_method_types')) {
            return;
        }

        Schema::table('payment_method_types', function (Blueprint $table) {
            if (!Schema::hasColumn('payment_method_types', 'is_active')) {
                $table->boolean('is_active')->default(true)
                      ->comment('Define si el método de pago está activo');
            }
        });
    }

    public function down()
    {
        if (!Schema::hasTable('payment_method_types')) {
            return;
        }

        Schema::table('payment_method_types', function (Blueprint $table) {
            if (Schema::hasColumn('payment_method_types', 'is_active')) {
                $table->dropColumn('is_active');
            }
        });
    }
};
