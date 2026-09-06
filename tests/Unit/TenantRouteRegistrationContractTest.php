<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

// ########## INICIO CAMBIO SIN XML CDR SUNAT
class TenantRouteRegistrationContractTest extends TestCase
{
    /** @test */
    public function core_routes_are_loaded_after_tenant_identification(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2).'/app/Providers/RouteServiceProvider.php');

        self::assertNotFalse($source);
        self::assertSame(2, substr_count($source, '$this->app->booted(function ()'));
        self::assertStringContainsString("->group(base_path('routes/web.php'))", $source);
        self::assertStringContainsString("->group(base_path('routes/api.php'))", $source);
    }

    /** @test */
    public function local_document_creation_route_remains_registered(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2).'/routes/web.php');

        self::assertNotFalse($source);
        self::assertStringContainsString("->name('tenant.documents.create')", $source);
        self::assertStringContainsString("Tenant\\DocumentController@create", $source);
    }

    /** @test */
    public function store_conversion_route_does_not_capture_document_creation_without_an_origin(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2).'/modules/Store/Routes/web.php');

        self::assertNotFalse($source);
        self::assertStringContainsString(
            "Route::get('create/{table}/{table_id}', 'StoreController@tableToDocument')",
            $source
        );
        self::assertStringNotContainsString('create/{table?}/{table_id?}', $source);
    }
}
// ######### FIN CAMBIO SIN XML CDR SUNAT
