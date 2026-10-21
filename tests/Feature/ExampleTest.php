<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     *
     * @return void
     */
    // ########### INICIO PRUEBA ACCESO RAÍZ PRO9
    public function testBasicTest()
    {
        // This anonymous system-route smoke test owns its hostname lookup fixture.
        // It must not depend on a developer database or bypass the tenancy middleware.
        config(['database.default'=>'system','database.connections.system'=>[
            'driver'=>'sqlite','database'=>':memory:','prefix'=>'',
        ],'tenancy.hostname.default'=>null]);
        DB::purge('system');
        Schema::connection('system')->create('hostnames',function ($table) {
            $table->bigIncrements('id');$table->string('fqdn');$table->unsignedBigInteger('website_id')->nullable();
            $table->softDeletes();
        });
        $response = $this->get('/');

        $response->assertRedirect('/login');
        self::assertSame(0,DB::connection('system')->table('hostnames')->count());
    }
    // ########### FIN PRUEBA ACCESO RAÍZ PRO9
}
