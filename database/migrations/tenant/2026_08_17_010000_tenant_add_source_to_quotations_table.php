<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TenantAddSourceToQuotationsTable extends Migration
{
    /**
     * Separación lógica: cotizaciones de empresa (admin) vs tienda virtual (ecommerce).
     */
    public function up()
    {
        Schema::table('quotations', function (Blueprint $table) {
            if (! Schema::hasColumn('quotations', 'source')) {
                $table->string('source', 20)
                    ->default('admin')
                    ->after('referential_information')
                    ->index();
            }
        });

        // Marca como ecommerce las ya creadas desde la tienda
        if (Schema::hasColumn('quotations', 'source')) {
            DB::table('quotations')
                ->where('referential_information', 'ecommerce')
                ->update([
                    'source' => 'ecommerce',
                    'referential_information' => 'Tienda virtual',
                ]);
        }
    }

    public function down()
    {
        Schema::table('quotations', function (Blueprint $table) {
            if (Schema::hasColumn('quotations', 'source')) {
                $table->dropColumn('source');
            }
        });
    }
}
