<?php

use App\Models\Tenant\Quotation;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TenantBackfillEcommerceQuotationNumbers extends Migration
{
    /**
     * Asigna correlativo propio (series COTV + number) a cotizaciones ecommerce existentes.
     */
    public function up()
    {
        if (! Schema::hasColumn('quotations', 'number') || ! Schema::hasColumn('quotations', 'series')) {
            return;
        }

        $rows = DB::table('quotations')
            ->where(function ($q) {
                $q->where('source', Quotation::SOURCE_ECOMMERCE)
                    ->orWhere('referential_information', 'ecommerce')
                    ->orWhere('referential_information', 'Tienda virtual');
            })
            ->where(function ($q) {
                $q->whereNull('number')->orWhere('number', 0);
            })
            ->orderBy('date_of_issue')
            ->orderBy('id')
            ->get(['id', 'date_of_issue', 'source']);

        $counters = [];

        foreach ($rows as $row) {
            $year = $row->date_of_issue
                ? date('Y', strtotime($row->date_of_issue))
                : date('Y');

            if (! isset($counters[$year])) {
                $counters[$year] = (int) DB::table('quotations')
                    ->where('source', Quotation::SOURCE_ECOMMERCE)
                    ->where('series', Quotation::SERIES_ECOMMERCE)
                    ->whereYear('date_of_issue', $year)
                    ->where('number', '>', 0)
                    ->max('number');
            }

            $counters[$year]++;

            DB::table('quotations')->where('id', $row->id)->update([
                'source' => Quotation::SOURCE_ECOMMERCE,
                'series' => Quotation::SERIES_ECOMMERCE,
                'number' => $counters[$year],
                'referential_information' => 'Tienda virtual',
            ]);
        }
    }

    public function down()
    {
        // No revierte números asignados (irreversible de forma segura).
    }
}
