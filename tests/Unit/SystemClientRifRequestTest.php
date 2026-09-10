<?php

namespace Tests\Unit;

use App\Http\Requests\System\ClientRequest;
use App\Http\Requests\System\ClientUpdateRequest;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

// ########## INICIO CAMBIO RIF SUPER ADMIN
class SystemClientRifRequestTest extends TestCase
{
    private array $originalSystemConnection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalSystemConnection = config('database.connections.system');

        config()->set('database.connections.system', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        DB::purge('system');
        Schema::connection('system')->create('clients', static function (Blueprint $table): void {
            $table->increments('id');
            $table->string('number')->unique();
            $table->string('name')->unique();
        });
    }

    protected function tearDown(): void
    {
        DB::purge('system');
        config()->set('database.connections.system', $this->originalSystemConnection);
        DB::purge('system');
        parent::tearDown();
    }

    /** @test */
    public function create_request_normalizes_and_accepts_a_valid_unique_rif(): void
    {
        $request = ClientRequest::create('/', 'POST', $this->validCreateData([
            'number' => 'j-123.456 789',
        ]));
        $this->resolve($request);

        self::assertSame('J123456789', $request->input('number'));
    }

    /** @test */
    public function create_request_rejects_a_duplicate_rif(): void
    {
        DB::connection('system')->table('clients')->insert([
            'number' => 'J123456789',
            'name' => 'CLIENTE EXISTENTE',
        ]);
        $request = ClientRequest::create('/', 'POST', $this->validCreateData([
            'number' => 'j-123.456 789',
        ]));

        $this->expectException(ValidationException::class);
        $this->resolve($request);
    }

    /** @test */
    public function update_ignores_the_current_client_but_rejects_another_clients_rif(): void
    {
        DB::connection('system')->table('clients')->insert([
            ['id' => 1, 'number' => 'J123456789', 'name' => 'CLIENTE UNO'],
            ['id' => 2, 'number' => 'V987654321', 'name' => 'CLIENTE DOS'],
        ]);

        $ownRequest = ClientUpdateRequest::create('/', 'POST', [
            'id' => 1,
            'fiscal_emission_mode' => 'free_form',
            'fiscal_environment' => 'demo',
            'number' => 'j-123.456 789',
        ]);
        $this->resolve($ownRequest);
        self::assertSame('J123456789', $ownRequest->input('number'));

        $duplicateRequest = ClientUpdateRequest::create('/', 'POST', [
            'id' => 1,
            'fiscal_emission_mode' => 'free_form',
            'fiscal_environment' => 'demo',
            'number' => 'V987654321',
        ]);

        try {
            $this->resolve($duplicateRequest);
            self::fail('Se esperaba rechazo por RIF duplicado.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('number', $exception->errors());
        }
    }

    private function validCreateData(array $overrides = []): array
    {
        return array_merge([
            'email' => 'admin@example.test',
            'number' => 'J123456789',
            'name' => 'CLIENTE NUEVO',
            'password' => 'Password1!',
            'subdomain' => 'clientedemo',
            'plan_id' => 1,
            'type' => 'admin',
            'fiscal_emission_mode' => 'free_form',
            'fiscal_environment' => 'demo',
        ], $overrides);
    }

    private function resolve($request): void
    {
        $request->setContainer($this->app);
        $request->setRedirector($this->app->make('redirect'));
        $request->validateResolved();
    }
}
// ######### FIN CAMBIO RIF SUPER ADMIN
