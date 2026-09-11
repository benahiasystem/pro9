<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Añade ecommerce_as_home a configuration_ecommerce: cuando está activo, la raíz del
 * subdominio del tenant (ej. demo.pro9.test) redirige a la tienda virtual en lugar
 * del dashboard o el login.
 */
class TenantAddEcommerceAsHomeToConfigurationEcommerce extends Migration
{
    public function up()
    {
        if (Schema::hasTable('configuration_ecommerce') && ! Schema::hasColumn('configuration_ecommerce', 'ecommerce_as_home')) {
            Schema::table('configuration_ecommerce', function (Blueprint $table) {
                $table->boolean('ecommerce_as_home')->default(false)->after('enable_store_pickup');
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('configuration_ecommerce') && Schema::hasColumn('configuration_ecommerce', 'ecommerce_as_home')) {
            Schema::table('configuration_ecommerce', function (Blueprint $table) {
                $table->dropColumn('ecommerce_as_home');
            });
        }
    }
}
