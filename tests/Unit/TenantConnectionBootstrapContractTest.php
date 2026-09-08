<?php

namespace Tests\Unit;

use App\Http\Kernel;
use App\Http\Middleware\EnsureTenantConnection;
use ReflectionClass;
use Tests\TestCase;

// ########## INICIO CORRECCIÓN INICIALIZACIÓN TENANT ##########

class TenantConnectionBootstrapContractTest extends TestCase
{
    /** @test */
    public function tenant_connection_recovery_runs_in_the_global_stack_before_authentication(): void
    {
        $middleware = (new ReflectionClass(Kernel::class))->getDefaultProperties()['middleware'];

        self::assertContains(EnsureTenantConnection::class, $middleware);
        self::assertLessThan(
            array_search(\Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance::class, $middleware, true),
            array_search(EnsureTenantConnection::class, $middleware, true)
        );
    }

    /** @test */
    public function provider_and_client_lifecycle_preserve_the_bootstrap_contract(): void
    {
        $provider = $this->source('app/Providers/AppServiceProvider.php');
        $client = $this->source('app/Http/Controllers/System/ClientController.php');
        $recovery = $this->source('app/Http/Middleware/EnsureTenantConnection.php');

        self::assertStringContainsString('$this->app->booted(function (): void', $provider);
        self::assertStringContainsString("! config('database.connections.tenant')", $provider);
        self::assertStringContainsString('->tenant($hostname->website);', $provider);
        self::assertStringContainsString('SessionLifetimeHelper::setTenantSessionLifetime();', $provider);
        self::assertStringContainsString('if (! config(\'database.connections.tenant\'))', $recovery);
        self::assertStringContainsString('$tenancy->tenant($hostname->website);', $recovery);

        foreach ([
            'tenancy.hostname.{$fqdn}',
            'tenancy.website.{$uuid}',
            'tenant_session_lifetime_{$fqdn}',
        ] as $key) {
            self::assertStringContainsString($key, $client);
        }
    }

    /** @test */
    public function adaptation_skill_records_the_first_access_invariant(): void
    {
        $skill = $this->source('.codex/skills/adaptar-sistema-venezuela/SKILL.md');

        self::assertStringContainsString('## Alta y primer acceso de un tenant', $skill);
        self::assertStringContainsString('TenantConnectionBootstrapContractTest', $skill);
    }

    private function source(string $path): string
    {
        self::assertFileExists(base_path($path));

        return (string) file_get_contents(base_path($path));
    }
}

// ######### FIN CORRECCIÓN INICIALIZACIÓN TENANT ##########
