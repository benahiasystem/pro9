<?php

namespace Tests\Unit;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Finance\Http\Controllers\UnpaidController;
use Tests\TestCase;

class UnpaidPaymentMethodFilterTest extends TestCase
{
    public function test_filter_includes_custom_and_inactive_historical_methods(): void
    {
        DB::purge('tenant');
        config(['database.connections.tenant'=>['driver'=>'sqlite','database'=>':memory:','prefix'=>'']]);
        Schema::connection('tenant')->create('persons', function (Blueprint $table) { $table->increments('id'); $table->string('type'); $table->string('name'); });
        Schema::connection('tenant')->create('establishments', function (Blueprint $table) { $table->increments('id'); });
        Schema::connection('tenant')->create('users', function (Blueprint $table) { $table->increments('id'); $table->string('type'); });
        Schema::connection('tenant')->create('web_platforms', function (Blueprint $table) { $table->increments('id'); });
        Schema::connection('tenant')->create('payment_method_types', function (Blueprint $table) {
            $table->string('id')->primary(); $table->string('description'); $table->string('collection_kind'); $table->boolean('is_active');
        });
        DB::connection('tenant')->table('payment_method_types')->insert([
            ['id'=>'09','description'=>'Reclasificado','collection_kind'=>'account','is_active'=>1],
            ['id'=>'14','description'=>'Crédito personalizado','collection_kind'=>'credit','is_active'=>1],
            ['id'=>'20','description'=>'Anterior inactivo','collection_kind'=>'unclassified','is_active'=>0],
        ]);
        $result=(new UnpaidController())->filter();
        self::assertEqualsCanonicalizing(['09','14','20'], $result['payment_method_types']->pluck('id')->all());
    }
}
