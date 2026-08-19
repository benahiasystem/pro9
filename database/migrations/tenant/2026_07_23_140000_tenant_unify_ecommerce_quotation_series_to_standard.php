<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 3: unifica correlativo de cotizaciones ecommerce al motor estándar COT-{id}.
 * Elimina serie COTV / número anual y el índice único asociado.
 */
class TenantUnifyEcommerceQuotationSeriesToStandard extends Migration
{
    public function up()
    {
        $indexName = 'quotations_series_number_year_number_unique';
        $exists = collect(DB::select('SHOW INDEX FROM quotations WHERE Key_name = ?', [$indexName]))->isNotEmpty();
        if ($exists) {
            Schema::table('quotations', function (Blueprint $table) use ($indexName) {
                $table->dropUnique($indexName);
            });
        }

        if (! Schema::hasColumn('quotations', 'source')) {
            return;
        }

        DB::table('quotations')
            ->where('source', 'ecommerce')
            ->orderBy('id')
            ->chunkById(100, function ($rows) {
                foreach ($rows as $row) {
                    $prefix = $row->prefix ?: 'COT';
                    $issue = $row->date_of_issue
                        ? date('Ymd', strtotime($row->date_of_issue))
                        : date('Ymd');

                    DB::table('quotations')->where('id', $row->id)->update([
                        'prefix' => $prefix,
                        'series' => '',
                        'number' => 0,
                        'number_year' => null,
                        'filename' => $prefix.'-'.$row->id.'-'.$issue,
                    ]);
                }
            });
    }

    public function down()
    {
        // No se recrea el correlativo COT-TV: la nomenclatura fue descartada.
    }
}
