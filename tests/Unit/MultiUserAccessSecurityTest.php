<?php

namespace Tests\Unit;

use App\Models\System\Client;
use App\Models\Tenant\User;
use Hyn\Tenancy\Contracts\CurrentHostname;
use Hyn\Tenancy\Database\Connection as TenancyConnection;
use Hyn\Tenancy\Environment;
use Hyn\Tenancy\Models\Hostname;
use Hyn\Tenancy\Models\Website;
use Illuminate\Config\Repository;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\Routing\RouteCollection;
use Illuminate\Routing\UrlGenerator;
use Illuminate\Support\Facades\Facade;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;
use Illuminate\Validation\ValidationException;
use Mockery;
use Modules\MultiUser\Helpers\Tenant\AutoLoginHelper;
use Modules\MultiUser\Http\Controllers\Tenant\Api\MultiUserController as ApiController;
use Modules\MultiUser\Http\Controllers\Tenant\MultiUserController as WebController;
use Modules\MultiUser\Http\Requests\Tenant\Api\ChangeClientRequest as ApiRequest;
use Modules\MultiUser\Http\Requests\Tenant\ChangeClientRequest;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Integración aislada: modelos y consultas reales sobre cuatro SQLite :memory:.
 * No arranca Kernel, .env, proveedores del proyecto ni conexiones/colas reales.
 */
class MultiUserAccessSecurityTest extends TestCase
{
    private $app;
    private $db;
    private $cache;
    private $auth;
    private int $tenant = 1;
    private array $switches = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->app = new Application(dirname(__DIR__, 2));
        $this->app->instance('config', new Repository([
            'database' => ['default' => 'system', 'connections' => []],
            'tenancy' => ['models' => ['website' => Website::class]],
            'tenant' => ['force_https' => true],
        ]));
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($this->app);
        $this->db = new Capsule($this->app);
        foreach (['system', 'tenant1', 'tenant2', 'tenant3'] as $name) {
            $this->db->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''], $name);
        }
        $this->db->bootEloquent();
        $this->db->getDatabaseManager()->setDefaultConnection('system');
        $this->app->instance('db', $this->db->getDatabaseManager());

        $connection = Mockery::mock(TenancyConnection::class);
        $connection->shouldReceive('systemName')->andReturn('system');
        $connection->shouldReceive('tenantName')->andReturnUsing(fn () => 'tenant'.$this->tenant);
        $this->app->instance(TenancyConnection::class, $connection);
        $environment = Mockery::mock(Environment::class);
        $environment->shouldReceive('tenant')->andReturnUsing(function ($website = null) {
            if ($website) {
                $this->tenant = (int) $website->id;
                $this->switches[] = $this->tenant;
            }
            return Website::findOrFail($this->tenant);
        });
        $this->app->instance(Environment::class, $environment);

        $this->cache = new MultiUserMemoryCache();
        $this->app->instance('cache', $this->cache);
        $this->auth = new class {
            public $current;
            public array $logins = [];
            public function guard($name = null) {
                return $name === 'admin' ? new class {
                    public function user() { return null; }
                    public function check() { return false; }
                } : $this;
            }
            public function user() { return $this->current; }
            public function loginUsingId($id) { $this->logins[] = $id; return true; }
        };
        $this->app->instance('auth', $this->auth);
        $validator = new Factory(new Translator(new ArrayLoader(), 'es'), $this->app);
        $this->app->instance('validator', $validator);
        $this->app->instance(\Illuminate\Contracts\Validation\Factory::class, $validator);
        $url = new UrlGenerator(new RouteCollection(), Request::create('https://tenant1.test/multi-users'));
        $this->app->instance('url', $url);
        $this->app->instance('redirect', new Redirector($url));
        $this->createFixtures();
        $this->useActor(1, 10);
    }

    private function createFixtures(): void
    {
        $schema = $this->db->getConnection('system')->getSchemaBuilder();
        $schema->create('multi_users', function (Blueprint $table) {
            $table->integer('id')->primary();
            foreach (['origin_client_id', 'origin_user_id', 'destination_client_id', 'destination_user_id'] as $key) {
                $table->integer($key);
            }
        });
        $schema->create('clients', function (Blueprint $table) {
            $table->integer('id')->primary();
            $table->integer('hostname_id');
            $table->string('name');
            $table->string('number');
        });
        $schema->create('plans', function (Blueprint $table) {
            $table->integer('id')->primary();
        });
        $schema->create('hostnames', function (Blueprint $table) {
            $table->integer('id')->primary();
            $table->integer('website_id');
            $table->string('fqdn');
            $table->timestamp('deleted_at')->nullable();
        });
        $schema->create('websites', function (Blueprint $table) {
            $table->integer('id')->primary();
            $table->string('uuid');
            $table->timestamp('deleted_at')->nullable();
        });
        foreach ([1, 2, 3] as $id) {
            $central = $this->db->getConnection('system');
            $central->table('clients')->insert(['id' => $id, 'hostname_id' => $id, 'name' => 'Empresa '.$id, 'number' => 'J'.$id]);
            $central->table('hostnames')->insert(['id' => $id, 'website_id' => $id, 'fqdn' => 'tenant'.$id.'.test']);
            $central->table('websites')->insert(['id' => $id, 'uuid' => 'tenant'.$id]);
            $schema = $this->db->getConnection('tenant'.$id)->getSchemaBuilder();
            $schema->create('users', function (Blueprint $table) {
                $table->integer('id')->primary();
                $table->boolean('active')->default(true);
                $table->boolean('is_multi_user')->default(false);
                $table->integer('multi_user_id')->nullable();
                $table->string('api_token')->nullable();
                $table->string('name')->default('Usuario sintético');
                $table->string('email')->default('user@example.test');
                $table->integer('establishment_id')->default(1);
            });
            // IDs locales coincidentes deliberadamente.
            $this->db->getConnection('tenant'.$id)->table('users')->insert([
                ['id' => 10, 'api_token' => 'SYNTHETIC-'.$id.'-10'],
                ['id' => 20, 'api_token' => 'SYNTHETIC-'.$id.'-20'],
            ]);
            $schema->create('companies', function (Blueprint $table) {
                $table->integer('id')->primary();
                $table->string('name');
                $table->string('number');
            });
            $schema->create('cat_identity_document_types', function (Blueprint $table) {
                $table->string('id')->primary();
            });
            $schema->create('app_configurations', function (Blueprint $table) {
                $table->integer('id')->primary();
            });
            $schema->create('establishments', function (Blueprint $table) {
                $table->integer('id')->primary();
                foreach (['country_id', 'department_id', 'province_id', 'district_id'] as $key) {
                    $table->string($key)->default('1');
                }
                $table->string('address');
                $table->string('telephone')->default('0000');
                $table->string('email')->default('office@example.test');
            });
            foreach (['countries', 'departments', 'provinces', 'districts'] as $catalog) {
                $schema->create($catalog, function (Blueprint $table) {
                    $table->string('id')->primary();
                    $table->string('description');
                });
                $this->db->getConnection('tenant'.$id)->table($catalog)->insert(['id' => '1', 'description' => $catalog.'-'.$id]);
            }
            $this->db->getConnection('tenant'.$id)->table('companies')->insert(['id' => 1, 'name' => 'Empresa '.$id, 'number' => 'J'.$id]);
            $this->db->getConnection('tenant'.$id)->table('establishments')->insert(['id' => 1, 'address' => 'Dirección '.$id]);
        }
        $this->db->getConnection('system')->table('multi_users')->insert([
            ['id' => 101, 'origin_client_id' => 1, 'origin_user_id' => 10, 'destination_client_id' => 2, 'destination_user_id' => 10],
            ['id' => 102, 'origin_client_id' => 1, 'origin_user_id' => 10, 'destination_client_id' => 3, 'destination_user_id' => 10],
            ['id' => 201, 'origin_client_id' => 1, 'origin_user_id' => 20, 'destination_client_id' => 2, 'destination_user_id' => 20],
        ]);
        foreach ([2 => 101, 3 => 102] as $tenant => $association) {
            $this->db->getConnection('tenant'.$tenant)->table('users')->where('id', 10)
                ->update(['is_multi_user' => true, 'multi_user_id' => $association]);
        }
    }

    private function useActor(int $tenant, int $userId): User
    {
        $this->tenant = $tenant;
        $this->cache->setPrefix('tenant'.$tenant);
        $this->app->instance(CurrentHostname::class, Hostname::findOrFail($tenant));
        return $this->auth->current = User::whereFilterWithOutRelations()->findOrFail($userId);
    }

    private function request(array $data, bool $api = false, bool $ajax = false): ChangeClientRequest
    {
        $class = $api ? ApiRequest::class : ChangeClientRequest::class;
        $request = $class::create('/multi-users', 'POST', $data);
        $request->headers->set('Accept', 'application/json');
        if ($ajax) $request->headers->set('X-Requested-With', 'XMLHttpRequest');
        $request->setUserResolver(fn () => $this->auth->current);
        $request->setContainer($this->app)->setRedirector($this->app['redirect']);
        $request->validateResolved();
        return $request;
    }

    private function assertForbidden(callable $action): void
    {
        try {
            $action();
            self::fail('Se esperaba rechazo antes de generar acceso.');
        } catch (HttpException $e) {
            self::assertSame(403, $e->getStatusCode());
        }
    }

    public function test_web_denies_another_users_association_with_and_without_ajax(): void
    {
        foreach ([false, true] as $ajax) {
            $this->assertForbidden(fn () => (new WebController())->changeClient($this->request([
                'multi_user_id' => 201, 'is_destination' => 'true',
            ], false, $ajax)));
        }
        self::assertSame([], $this->cache->writes);
        self::assertSame([], $this->switches);
    }

    public function test_matching_local_user_id_in_unrelated_tenant_is_not_authorization(): void
    {
        $this->useActor(3, 20);
        $this->assertForbidden(fn () => (new WebController())->changeClient($this->request([
            'multi_user_id' => 201, 'is_destination' => true,
        ])));
        self::assertSame([], $this->cache->writes);
    }

    public function test_web_preserves_outbound_return_and_sibling_company_choices(): void
    {
        foreach ([[1, 101, 'true', 2], [2, 101, 'false', 1], [2, 102, 'true', 3]] as [$source, $id, $direction, $destination]) {
            $this->useActor($source, 10);
            $response = (new WebController())->changeClient($this->request(['multi_user_id' => $id, 'is_destination' => $direction]));
            self::assertStringStartsWith('https://tenant'.$destination.'.test/auto-login/', $response->getTargetUrl());
            $pending = json_decode($this->cache->get('auto_login_tenant'.$destination.'.test'));
            self::assertSame($id, $pending->multi_user_id);
            self::assertSame($direction === 'true', $pending->is_destination);
            self::assertSame(1, $pending->association->origin_client_id);
        }
    }

    public function test_reversed_direction_and_unknown_ids_fail_closed(): void
    {
        foreach ([[1, 101, false], [2, 102, false], [1, 999, true]] as [$source, $id, $direction]) {
            $this->useActor($source, 10);
            $this->assertForbidden(fn () => (new WebController())->changeClient($this->request([
                'multi_user_id' => $id, 'is_destination' => $direction,
            ])));
        }
        self::assertSame([], $this->cache->writes);
    }

    public function test_spoofed_or_revoked_mirror_anchor_cannot_grant_sibling_access(): void
    {
        $this->useActor(2, 20);
        $this->auth->current->is_multi_user = true;
        $this->auth->current->multi_user_id = 101;
        $this->assertForbidden(fn () => (new WebController())->changeClient($this->request(['multi_user_id' => 102, 'is_destination' => true])));
        $this->useActor(2, 10);
        $this->db->getConnection('system')->table('multi_users')->where('id', 101)->delete();
        $this->assertForbidden(fn () => (new WebController())->changeClient($this->request(['multi_user_id' => 102, 'is_destination' => true])));
        self::assertSame([], $this->cache->writes);
    }

    public function test_listing_preserves_linked_companies_but_ignores_invalid_mirror_anchor(): void
    {
        $this->useActor(2, 10);
        $records = (new WebController())->records();
        self::assertEqualsCanonicalizing([null, 101, 102], $records->pluck('id')->all());
        $this->auth->current->multi_user_id = 201;
        self::assertSame([null], (new WebController())->records()->pluck('id')->all());
    }

    public function test_api_denies_unauthorized_association_before_switch_or_token_return(): void
    {
        $this->assertForbidden(fn () => (new ApiController())->changeClient($this->request([
            'multi_user_id' => 201, 'is_destination' => true, 'fqdn' => 'tenant2.test',
        ], true)));
        self::assertSame([], $this->switches);
    }

    public function test_api_preserves_correct_destination_user_and_restores_source_context(): void
    {
        foreach ([[1, 101, true, 2], [2, 101, 'false', 1], [2, 102, 1, 3]] as [$source, $id, $direction, $destination]) {
            $this->useActor($source, 10);
            $result = (new ApiController())->changeClient($this->request([
                'multi_user_id' => $id, 'is_destination' => $direction, 'fqdn' => 'tenant'.$destination.'.test',
            ], true));
            self::assertSame('SYNTHETIC-'.$destination.'-10', $result['api_token']);
            self::assertTrue($result['success']);
            self::assertSame('Empresa '.$destination, $result['company']['name']);
            self::assertStringEndsWith('Dirección '.$destination, $result['company']['address']);
            self::assertSame(1, $result['establishment_id']);
            self::assertSame($source, $this->tenant);
        }
    }

    public function test_api_wrong_hostname_is_rejected_before_switch(): void
    {
        $this->assertForbidden(fn () => (new ApiController())->changeClient($this->request([
            'multi_user_id' => 101, 'is_destination' => true, 'fqdn' => 'tenant3.test',
        ], true)));
        self::assertSame([], $this->switches);
    }

    public function test_disabled_destination_cannot_receive_login_or_return_token(): void
    {
        $this->db->getConnection('tenant2')->table('users')->where('id', 10)->update(['active' => false]);
        $this->assertForbidden(fn () => (new ApiController())->changeClient($this->request([
            'multi_user_id' => 101, 'is_destination' => true, 'fqdn' => 'tenant2.test',
        ], true)));
        self::assertSame(1, $this->tenant);
        (new WebController())->changeClient($this->request(['multi_user_id' => 101, 'is_destination' => true]));
        $this->useActor(2, 10);
        $this->assertForbidden(fn () => (new AutoLoginHelper())->startProcess());
        self::assertSame([], $this->auth->logins);
    }

    public function test_web_handoff_rejects_association_changed_after_authorization(): void
    {
        (new WebController())->changeClient($this->request(['multi_user_id' => 101, 'is_destination' => true]));
        $this->db->getConnection('system')->table('multi_users')->where('id', 101)->update(['destination_user_id' => 20]);
        $this->useActor(2, 10);
        $this->assertForbidden(fn () => (new AutoLoginHelper())->startProcess());
        self::assertSame([], $this->auth->logins);
    }

    public function test_valid_web_handoff_authenticates_expected_user(): void
    {
        // Usuario admin id=1 ausente en fixtures: la sincronización real retorna sin efectos adicionales.
        foreach ([[1, 101, true, 2], [2, 101, false, 1], [2, 102, true, 3]] as [$source, $id, $direction, $destination]) {
            $this->useActor($source, 10);
            (new WebController())->changeClient($this->request(['multi_user_id' => $id, 'is_destination' => $direction]));
            $this->useActor($destination, 10);
            (new AutoLoginHelper())->startProcess();
            self::assertFalse($this->cache->has('auto_login_tenant'.$destination.'.test'));
        }
        self::assertSame([10, 10, 10], $this->auth->logins);
    }

    public function test_request_rejects_bad_ids_and_ambiguous_directions(): void
    {
        foreach ([['multi_user_id' => [101], 'is_destination' => true], ['multi_user_id' => 0, 'is_destination' => true],
            ['multi_user_id' => 101, 'is_destination' => 'yes'], ['multi_user_id' => 101, 'is_destination' => ['true']],
            ['multi_user_id' => 101]] as $data) {
            try {
                $this->request($data);
                self::fail('Se esperaba validación de entrada.');
            } catch (ValidationException $e) {
                self::assertNotEmpty($e->errors());
            }
        }
        self::assertSame([], $this->cache->writes);
    }

    public function test_inactive_source_is_denied_by_authorization_service(): void
    {
        $this->auth->current->active = false;
        $this->assertForbidden(fn () => (new WebController())->changeClient($this->request(['multi_user_id' => 101, 'is_destination' => true])));
        self::assertSame([], $this->cache->writes);
    }

    public function test_guest_request_cannot_authorize_a_change(): void
    {
        $this->auth->current = null;
        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);
        $this->request(['multi_user_id' => 101, 'is_destination' => true]);
    }

    public function test_api_keeps_no_token_response_and_restores_source(): void
    {
        $this->db->getConnection('tenant1')->table('users')->where('id', 10)->update(['api_token' => null]);
        $this->useActor(2, 10);
        $response = (new ApiController())->changeClient($this->request([
            'multi_user_id' => 101, 'is_destination' => '0', 'fqdn' => 'tenant1.test',
        ], true));
        self::assertFalse($response['success']);
        self::assertArrayNotHasKey('api_token', $response);
        self::assertSame(2, $this->tenant);
    }

    public function test_api_missing_destination_user_restores_source_without_login(): void
    {
        $this->db->getConnection('tenant2')->table('users')->where('id', 10)->delete();
        try {
            (new ApiController())->changeClient($this->request([
                'multi_user_id' => 101, 'is_destination' => true, 'fqdn' => 'tenant2.test',
            ], true));
            self::fail('No debe emitir credenciales para un usuario inexistente.');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            self::assertSame(1, $this->tenant);
            self::assertSame([], $this->auth->logins);
        }
    }

    public function test_revoked_association_cannot_be_redeemed(): void
    {
        (new WebController())->changeClient($this->request(['multi_user_id' => 101, 'is_destination' => true]));
        $this->db->getConnection('system')->table('multi_users')->where('id', 101)->delete();
        $this->useActor(2, 10);
        try {
            (new AutoLoginHelper())->startProcess();
            self::fail('No debe autenticar mediante un vínculo eliminado.');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            self::assertSame([], $this->auth->logins);
            self::assertFalse($this->cache->has('auto_login_tenant2.test'));
        }
    }

    protected function tearDown(): void
    {
        Mockery::close();
        foreach (['system', 'tenant1', 'tenant2', 'tenant3'] as $name) $this->db->getDatabaseManager()->purge($name);
        Model::unsetConnectionResolver();
        Model::clearBootedModels();
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication(null);
        $this->app->flush();
        Application::setInstance(null);
        parent::tearDown();
    }
}

class MultiUserMemoryCache
{
    private string $prefix = '';
    private array $values = [];
    public array $writes = [];
    public function setPrefix($prefix) { $this->prefix = $prefix; }
    public function put($key, $value, $ttl) { $this->writes[] = $key; $this->values[$this->prefix.$key] = $value; }
    public function has($key) { return isset($this->values[$this->prefix.$key]); }
    public function get($key) { return $this->values[$this->prefix.$key] ?? null; }
    public function forget($key) { unset($this->values[$this->prefix.$key]); }
}
