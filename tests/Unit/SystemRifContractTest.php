<?php

namespace Tests\Unit;

use Tests\TestCase;

// ########## INICIO CAMBIO RIF SUPER ADMIN
class SystemRifContractTest extends TestCase
{
    /** @test */
    public function requests_normalize_validate_and_keep_system_client_uniqueness(): void
    {
        foreach ([
            'app/Http/Requests/System/ClientRequest.php',
            'app/Http/Requests/System/ClientUpdateRequest.php',
        ] as $path) {
            $source = $this->source($path);
            self::assertStringContainsString('Rif::normalize', $source, $path);
            self::assertStringContainsString("Rule::unique('system.clients'", $source, $path);
            self::assertStringContainsString('Rif::PATTERN', $source, $path);
        }

        $update = $this->source('app/Http/Requests/System/ClientUpdateRequest.php');
        self::assertStringContainsString("->ignore(\$this->input('id'))", $update);
    }

    /** @test */
    public function rif_lookup_is_registered_once_and_only_inside_the_authenticated_system_group(): void
    {
        $webRoutes = $this->source('routes/web.php');
        $rifRoute = "Route::get('services/rif/{rif}', 'System\\ServiceController@rif')";
        $authGroup = "Route::middleware(['auth:admin', 'reseller.system.admin'])->group";

        self::assertSame(1, substr_count($webRoutes, $rifRoute));
        self::assertGreaterThan(strpos($webRoutes, $authGroup), strpos($webRoutes, $rifRoute));
        self::assertStringNotContainsString('services/rif', substr($webRoutes, 0, strpos($webRoutes, $authGroup)));
        self::assertStringNotContainsString('services/rif', $this->source('routes/api.php'));

        $route = collect(app('router')->getRoutes()->getRoutes())
            ->first(static fn ($route) => substr_compare(
                $route->getActionName(),
                'System\\ServiceController@rif',
                -strlen('System\\ServiceController@rif')
            ) === 0);
        self::assertNotNull($route);
        self::assertContains('auth:admin', $route->gatherMiddleware());
        self::assertContains('reseller.system.admin', $route->gatherMiddleware());
    }

    /** @test */
    public function super_admin_client_surfaces_present_rif_without_changing_soap_copy(): void
    {
        $expectations = [
            'resources/js/views/system/clients/form.vue' => '<label class="control-label">RIF</label>',
            'resources/js/views/system/clients/index.vue' => '<th v-if="columns.ruc.visible">RIF</th>',
            'resources/js/views/system/clients/partials/delete.vue' => 'Ingrese el RIF o nombre',
            'resources/js/views/system/clients/partials/account_status.vue' => '>RIF</label>',
            'resources/views/system/reports/index.blade.php' => "'RIF Cliente'",
            'resources/views/system/payment/show.blade.php' => 'RIF {{ $client[\'number\'] }}',
        ];

        foreach ($expectations as $path => $needle) {
            self::assertStringContainsString($needle, $this->source($path), $path);
        }

        $form = $this->source('resources/js/views/system/clients/form.vue');
        self::assertStringContainsString('RUC + Usuario. Ejemplo:', $form);
        self::assertStringContainsString("soap_sends: [{value: '01', text: 'Sunat'}", $form);
        self::assertStringNotContainsString('<x-input-service class="btn-sunat-reniec-container"', $form);
        self::assertStringContainsString('<rif-input', $form);
    }

    /** @test */
    public function provider_configuration_is_disabled_by_default_and_never_exposed_to_vue(): void
    {
        $environment = $this->source('.env.example');
        self::assertStringContainsString('SUPER_ADMIN_RIF_LOOKUP_ENABLED=false', $environment);
        self::assertStringContainsString('SUPER_ADMIN_RIF_LOOKUP_URL=', $environment);
        self::assertStringContainsString('SUPER_ADMIN_RIF_LOOKUP_TOKEN=', $environment);
        self::assertStringContainsString('SUPER_ADMIN_RIF_LOOKUP_TIMEOUT=10', $environment);

        $form = $this->source('resources/js/views/system/clients/form.vue');
        self::assertStringContainsString('rif_lookup_available', $form);
        self::assertStringNotContainsString('SUPER_ADMIN_RIF_LOOKUP_TOKEN', $form);
        self::assertStringNotContainsString('SUPER_ADMIN_RIF_LOOKUP_URL', $form);
    }

    /** @test */
    public function changed_sources_keep_balanced_rif_markers(): void
    {
        $startMarker = '########## INICIO CAMBIO RIF SUPER ADMIN';
        $endMarker = '######### FIN CAMBIO RIF SUPER ADMIN';

        foreach ($this->changedSources() as $path) {
            $source = $this->source($path);
            self::assertGreaterThan(0, substr_count($source, $startMarker), $path);
            self::assertSame(
                substr_count($source, $startMarker),
                substr_count($source, $endMarker),
                $path
            );
        }
    }

    private function changedSources(): array
    {
        return [
            '.env.example',
            'app/Exceptions/System/RifLookupException.php',
            'app/Http/Controllers/System/ClientController.php',
            'app/Http/Controllers/System/ServiceController.php',
            'app/Http/Requests/System/ClientRequest.php',
            'app/Http/Requests/System/ClientUpdateRequest.php',
            'app/Services/System/RifLookupService.php',
            'app/Support/System/Rif.php',
            'config/services.php',
            'resources/js/views/system/clients/form.vue',
            'resources/js/views/system/clients/index.vue',
            'resources/js/views/system/clients/partials/RifInput.vue',
            'resources/js/views/system/clients/partials/account_status.vue',
            'resources/js/views/system/clients/partials/delete.vue',
            'resources/js/views/system/payments/payment-view.vue',
            'resources/views/system/payment/show.blade.php',
            'resources/views/system/reports/index.blade.php',
            'routes/web.php',
        ];
    }

    private function source(string $path): string
    {
        self::assertFileExists(base_path($path));

        return (string) file_get_contents(base_path($path));
    }
}
// ######### FIN CAMBIO RIF SUPER ADMIN
