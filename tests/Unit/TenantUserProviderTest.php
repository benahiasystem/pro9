<?php

namespace Tests\Unit;

use App\Auth\TenantUserProvider;
use App\Http\Controllers\Tenant\PosController;
use App\Models\Tenant\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TenantUserProviderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();DB::purge('tenant');
        config(['database.connections.tenant'=>['driver'=>'sqlite','database'=>':memory:','prefix'=>'']]);
        $db=DB::connection('tenant');$schema=$db->getSchemaBuilder();
        $schema->create('users',function ($t) {$t->increments('id');$t->integer('establishment_id');$t->string('type');$t->string('name');$t->string('email');$t->string('api_token');$t->string('password');$t->boolean('active');});
        foreach (['countries','departments','provinces','districts'] as $table) $schema->create($table,function ($t) {$t->string('id')->primary();$t->string('description');});
        $schema->create('establishments',function ($t) {$t->increments('id');$t->string('country_id');$t->string('department_id');$t->string('province_id');$t->string('district_id');});
        foreach (['countries'=>'VE','departments'=>'14','provinces'=>'0229','districts'=>'000619'] as $table=>$id) $db->table($table)->insert(['id'=>$id,'description'=>'Temporal']);
        $db->table('establishments')->insert(['id'=>1,'country_id'=>'VE','department_id'=>'14','province_id'=>'0229','district_id'=>'000619']);
        $db->table('users')->insert(['id'=>1,'establishment_id'=>1,'type'=>'seller','name'=>'Temporal','email'=>'seller@example.test','api_token'=>'temporary-token','password'=>app('hash')->make('temporary-password'),'active'=>true]);
    }

    private function route(string $action): void
    {
        $request=Request::create('/fixture');
        $request->setRouteResolver(static fn()=>new Route('GET','fixture',['uses'=>$action,'controller'=>$action]));
        app()->instance('request',$request);
    }

    public function test_pos_authentication_defers_geography_but_keeps_identity_credentials_and_lazy_relations(): void
    {
        $this->route(PosController::class.'@item');
        $provider=new TenantUserProvider(app('hash'),User::class);
        $db=DB::connection('tenant');$db->enableQueryLog();$db->flushQueryLog();
        $user=$provider->retrieveById(1);
        self::assertInstanceOf(User::class,$user);
        self::assertSame(1,$user->id);self::assertSame(1,$user->establishment_id);self::assertSame('seller',$user->type);
        self::assertFalse($user->relationLoaded('establishment'));
        self::assertCount(1,$db->getQueryLog());
        self::assertTrue($provider->validateCredentials($user,['password'=>'temporary-password']));
        self::assertFalse($provider->validateCredentials($user,['password'=>'incorrect']));
        self::assertSame('VE',$user->establishment->country->id);
        self::assertTrue($user->relationLoaded('establishment'));
        self::assertNull($provider->retrieveById(9999));
        self::assertSame(1,$provider->retrieveByCredentials(['api_token'=>'temporary-token'])->id);
        self::assertNull($provider->retrieveByCredentials(['api_token'=>'incorrect']));
        $db->disableQueryLog();
    }

    public function test_other_routes_and_provider_callbacks_keep_the_original_queries_and_eager_relationship(): void
    {
        $this->route('App\\Http\\Controllers\\Tenant\\DocumentController@records');
        $provider=new TenantUserProvider(app('hash'),User::class);
        self::assertTrue($provider->retrieveById(1)->relationLoaded('establishment'));
        $this->route(PosController::class.'@item');
        $provider->withQuery(static fn($query)=>$query->where('active',false));
        self::assertNull($provider->retrieveById(1),'The normal provider query callback remains in force.');
        self::assertSame('eloquent',config('auth.providers.admins.driver'));
        self::assertSame('eloquent',config('auth.providers.customers.driver'));
    }
}
