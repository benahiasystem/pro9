<?php

use App\Models\Tenant\Quotation;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TenantAddNumberYearAndUniqueToQuotationsTable extends Migration
{
    public function up()
    {
        Schema::table('quotations', function (Blueprint $table) {
            if (! Schema::hasColumn('quotations', 'number_year')) {
                $table->unsignedSmallInteger('number_year')->nullable()->after('number');
            }
        });

        // Rellena number_year en cotizaciones ecommerce ya numeradas
        if (Schema::hasColumn('quotations', 'number_year')) {
            DB::table('quotations')
                ->where('source', Quotation::SOURCE_ECOMMERCE)
                ->where('number', '>', 0)
                ->whereNull('number_year')
                ->orderBy('id')
                ->chunkById(100, function ($rows) {
                    foreach ($rows as $row) {
                        $year = $row->date_of_issue
                            ? (int) date('Y', strtotime($row->date_of_issue))
                            : (int) date('Y');

                        DB::table('quotations')->where('id', $row->id)->update([
                            'number_year' => $year,
                            'series' => Quotation::SERIES_ECOMMERCE,
                        ]);
                    }
                });
        }

        // Índice único: series + año + número (admin con number_year NULL no colisiona en MySQL)
        $indexName = 'quotations_series_number_year_number_unique';
        $exists = collect(DB::select("SHOW INDEX FROM quotations WHERE Key_name = ?", [$indexName]))->isNotEmpty();
        if (! $exists && Schema::hasColumn('quotations', 'number_year')) {
            Schema::table('quotations', function (Blueprint $table) use ($indexName) {
                $table->unique(['series', 'number_year', 'number'], $indexName);
            });
        }
    }

    public function down()
    {
        $indexName = 'quotations_series_number_year_number_unique';
        $exists = collect(DB::select("SHOW INDEX FROM quotations WHERE Key_name = ?", [$indexName]))->isNotEmpty();
        if ($exists) {
            Schema::table('quotations', function (Blueprint $table) use ($indexName) {
                $table->dropUnique($indexName);
            });
        }

        Schema::table('quotations', function (Blueprint $table) {
            if (Schema::hasColumn('quotations', 'number_year')) {
                $table->dropColumn('number_year');
            }
        });
    }
}
