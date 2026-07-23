<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Parámetros de cotizaciones para la tienda virtual (configuration_ecommerce).
 */
class TenantAddQuotationSettingsToConfigurationEcommerce extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('configuration_ecommerce')) {
            return;
        }

        Schema::table('configuration_ecommerce', function (Blueprint $table) {
            if (!Schema::hasColumn('configuration_ecommerce', 'quotation_enabled')) {
                $table->boolean('quotation_enabled')->default(false)->after('enable_store_pickup');
            }
            if (!Schema::hasColumn('configuration_ecommerce', 'quotation_mode')) {
                $table->string('quotation_mode', 30)->default('quote_and_sell')->after('quotation_enabled');
            }
            if (!Schema::hasColumn('configuration_ecommerce', 'quotation_show_prices')) {
                $table->boolean('quotation_show_prices')->default(true)->after('quotation_mode');
            }
            if (!Schema::hasColumn('configuration_ecommerce', 'quotation_success_message')) {
                $table->text('quotation_success_message')->nullable()->after('quotation_show_prices');
            }
        });
    }

    public function down()
    {
        if (!Schema::hasTable('configuration_ecommerce')) {
            return;
        }

        $columns = array_values(array_filter([
            'quotation_enabled',
            'quotation_mode',
            'quotation_show_prices',
            'quotation_success_message',
        ], function ($column) {
            return Schema::hasColumn('configuration_ecommerce', $column);
        }));

        if (empty($columns)) {
            return;
        }

        Schema::table('configuration_ecommerce', function (Blueprint $table) use ($columns) {
            $table->dropColumn($columns);
        });
    }
}
