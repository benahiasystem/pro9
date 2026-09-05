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
}
// ######### FIN CAMBIO SIN XML CDR SUNAT
