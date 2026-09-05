<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vigencia automática y condiciones comerciales de cotizaciones (tienda virtual).
 */
class TenantAddQuotationValidityAndTermsToConfigurationEcommerce extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('configuration_ecommerce')) {
            return;
        }

        Schema::table('configuration_ecommerce', function (Blueprint $table) {
            if (!Schema::hasColumn('configuration_ecommerce', 'quotation_validity_days')) {
                $table->unsignedTinyInteger('quotation_validity_days')->default(7)->after('quotation_success_message');
            }
            if (!Schema::hasColumn('configuration_ecommerce', 'quotation_terms')) {
                $table->text('quotation_terms')->nullable()->after('quotation_validity_days');
            }
        });
    }

    public function down()
    {
        if (!Schema::hasTable('configuration_ecommerce')) {
            return;
        }

        $columns = array_values(array_filter([
            'quotation_validity_days',
            'quotation_terms',
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
