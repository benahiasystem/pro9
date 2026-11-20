<?php
namespace Tests\Unit;

use App\Database\{PostgresBackup,PostgresBackupManager,PostgresBootstrap,SchemaInspector,SequenceSynchronizer};
use App\Tenancy\TenantProvisioning;
use Illuminate\Support\Facades\{DB,Schema,Http};
use Tests\Support\PostgresTestEnvironment;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Real kernel, central migrations, least-privilege provisioning and native recovery. */
/** @group postgres-integration */
final class PostgresImplementationTest extends \Tests\TestCase
{
    private string $prefix;
    private $control;
    private array $owner;
    protected function setUp(): void
    {
        if (getenv('SAB_POSTGRES_TESTS')!=='1') $this->markTestSkipped('Use scripts/test_postgresql.php para una instancia temporal.');
        parent::setUp();
        $config=PostgresTestEnvironment::connection();
        config(['database.connections.pg_control'=>$config]);
        $this->control=DB::connection('pg_control');PostgresTestEnvironment::assertServer($this->control);
        $this->prefix='sab_native_'.bin2hex(random_bytes(5));
        $owner=$this->prefix.'_owner';$runtime=$this->prefix.'_runtime';$provisioner=$this->prefix.'_prov';$database=$this->prefix.'_central';
        $password=bin2hex(random_bytes(16));$quoted=$this->control->getPdo()->quote($password);
        foreach ([$owner,$runtime,$provisioner] as $role) $this->control->getPdo()->exec('CREATE ROLE "'.$role.'" LOGIN NOSUPERUSER '.($role===$provisioner?'CREATEDB CREATEROLE':'NOCREATEDB NOCREATEROLE').' PASSWORD '.$quoted);
        $this->control->statement('CREATE DATABASE "'.$database.'" OWNER "'.$owner.'" TEMPLATE template0');
        $this->control->statement('REVOKE ALL ON DATABASE "'.$database.'" FROM PUBLIC');
        $this->control->statement('GRANT CONNECT ON DATABASE "'.$database.'" TO "'.$runtime.'"');
        $this->owner=array_merge($config,['database'=>$database,'username'=>$owner,'password'=>$password]);
        config(['database.connections.central_migrator'=>$this->owner,'database.connections.system'=>array_merge($this->owner,['username'=>$runtime]),'database.connections.provisioning_ledger'=>array_merge($this->owner,['username'=>$runtime]),'database.connections.provisioner'=>array_merge($config,['username'=>$provisioner,'password'=>$password]),'database.default'=>'system']);
        foreach (['central_migrator','system','provisioner','provisioning_ledger'] as $name) DB::purge($name);
        $db=DB::connection('central_migrator');
        $db->statement('REVOKE ALL ON SCHEMA public FROM PUBLIC');$db->statement('GRANT USAGE ON SCHEMA public TO "'.$runtime.'"');
        $db->statement('ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT SELECT,INSERT,UPDATE,DELETE ON TABLES TO "'.$runtime.'"');
        $db->statement('ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT USAGE,SELECT ON SEQUENCES TO "'.$runtime.'"');
        Http::fake(static function () { throw new \RuntimeException('No se permite HTTP externo durante estas pruebas.'); });
    }
    private function install(): void
    {
        self::assertSame(0,$this->artisan('sab:install-postgres')->run());
        self::assertSame(count(glob(database_path('migrations/*.php')))+count(glob(base_path('modules/Marketplace/Database/Migrations/*.php'))),DB::connection('central_migrator')->table('migrations')->count());
    }
    private function tenant(string $suffix): array
    {
        $name=$this->prefix.'_'.$suffix;
        return array_merge($this->owner,['database'=>$name,'username'=>$name,'password'=>bin2hex(random_bytes(24))]);
    }
    private function connect(array $config,string $name='pg_tenant')
    {
        config(['database.connections.'.$name=>$config]);DB::purge($name);return DB::connection($name);
    }
    protected function tearDown(): void
    {
        try {
            if (isset($this->prefix,$this->control)) {
                foreach (DB::getConnections() as $name=>$db) {while ($db->transactionLevel()) $db->rollBack();if ($name!=='pg_control') $db->disconnect();}
                foreach ($this->control->select('SELECT datname FROM pg_database WHERE starts_with(datname,?)',[$this->prefix]) as $row) $this->control->statement('DROP DATABASE '.PostgresBootstrap::identifier($row->datname).' WITH (FORCE)');
                foreach ($this->control->select('SELECT rolname FROM pg_roles WHERE starts_with(rolname,?)',[$this->prefix]) as $row) $this->control->statement('DROP ROLE '.PostgresBootstrap::identifier($row->rolname));
            }
        } finally {parent::tearDown();}
    }
    public function test_version_clients_and_runtime_extensions(): void
    {
        self::assertSame(170011,(int)$this->control->selectOne('SHOW server_version_num')->server_version_num);
        self::assertStringContainsString('x86_64',$this->control->selectOne('SELECT version() AS version')->version);
        foreach (['pdo_pgsql','pgsql','gd','bcmath','intl','zip','pdo_sqlite','pcntl'] as $extension) self::assertTrue(extension_loaded($extension),$extension);
        self::assertTrue(gd_info()['AVIF Support']);self::assertFalse(extension_loaded('pdo_mysql'));
        foreach (['pg_dump','pg_restore','psql'] as $binary) {
            $p=new \Symfony\Component\Process\Process(['/usr/lib/postgresql/17/bin/'.$binary,'--version']);$p->mustRun();self::assertMatchesRegularExpression('/\b17\./',$p->getOutput());
        }
    }

    public function test_extra_services_reads_its_public_configuration_from_postgresql(): void
    {
        $this->install();
        $rows = DB::connection('system')->table('extra_services');
        self::assertSame(1, $rows->count());
        $settings = [
            'urlObtainApidocs' => 'https://extra-services.example.test/instructions',
            'isActiveApidocs' => false,
        ];
        self::assertSame(1, $rows->update($settings + ['urlServiceApidocs' => 'https://private.example.test']));
        $resource = app(\Modules\ExtraServices\Http\Controllers\ExtraServicesController::class)->records();
        self::assertSame($settings, $resource->resolve(\Illuminate\Http\Request::create('/extra-services/records')));
        self::assertSame(1, $rows->count());
    }

    /** @dataProvider databaseMaintenanceOperations */
    public function test_restricted_provisioner_can_maintain_a_database_during_autovacuum(bool $rename): void
    {
        $this->install();
        $service = app(TenantProvisioning::class);
        $config = $this->tenant('vacuum');
        $name = $config['database'];
        $previous = $this->control->selectOne("SELECT current_setting('autovacuum_naptime') AS value")->value;
        $this->control->statement("ALTER SYSTEM SET autovacuum_naptime = '1s'");
        $this->control->selectOne('SELECT pg_reload_conf()');
        try {
            $service->create($config, null, static function () {}, false);
            $target = $this->connect($config, 'pg_vacuum_target');
            $target->statement('CREATE TABLE vacuum_probe (value text) WITH (
                autovacuum_vacuum_insert_threshold=0, autovacuum_vacuum_insert_scale_factor=0,
                autovacuum_vacuum_cost_delay=100, autovacuum_vacuum_cost_limit=1
            )');
            $target->statement("INSERT INTO vacuum_probe SELECT repeat(md5(g::text), 16) FROM generate_series(1,12000) g");
            DB::purge('pg_vacuum_target');
            $deadline = microtime(true) + 20;
            do {
                $worker = $this->control->selectOne("SELECT pid FROM pg_stat_activity WHERE datname=? AND backend_type='autovacuum worker'", [$name]);
                if ($worker) break;
                usleep(100000);
            } while (microtime(true) < $deadline);
            self::assertNotNull($worker, 'La base temporal debe tener un autovacuum activo antes de eliminarla.');
            $role = DB::connection('provisioner')->selectOne('SELECT usesysid FROM pg_stat_activity WHERE pid=?', [$worker->pid]);
            self::assertNotNull($role);
            self::assertNull($role->usesysid);
            if ($rename) {
                $destination = $this->tenant('vacuum_renamed');
                $service->rename($name, $destination, $config['password'], false);
                self::assertNull($this->control->selectOne('SELECT 1 FROM pg_database WHERE datname=?', [$name]));
                $restored = $this->connect($destination, 'pg_vacuum_target');
                self::assertSame(12000, $restored->table('vacuum_probe')->count());
                $name = $destination['database'];
            }
            $service->delete($name);
            self::assertNull($this->control->selectOne('SELECT 1 FROM pg_database WHERE datname=?', [$name]));
            self::assertNull($this->control->selectOne('SELECT 1 FROM pg_roles WHERE rolname=?', [$name]));
        } finally {
            DB::purge('pg_vacuum_target');
            $this->control->statement('ALTER SYSTEM SET autovacuum_naptime = '.$this->control->getPdo()->quote($previous));
            $this->control->selectOne('SELECT pg_reload_conf()');
        }
    }

    public static function databaseMaintenanceOperations(): array
    {
        return ['eliminar' => [false], 'renombrar' => [true]];
    }
    public function test_real_central_installation_seed_sequences_rollback_and_repeat(): void
    {
        $this->install();
        $this->artisan('db:seed',['--database'=>'central_migrator','--force'=>true])->assertExitCode(0);
        $db=DB::connection('central_migrator');SequenceSynchronizer::sync($db);
        self::assertSame(2,$db->table('plans')->count());
        $previous=$db->table('modules')->max('id');
        $row=$db->table('modules')->first();$data=(array)$row;unset($data['id']);
        if (isset($data['value'])) $data['value']='pg-sequence-test';
        self::assertGreaterThan($previous,$db->table('modules')->insertGetId($data));
        $first=SchemaInspector::snapshot($db);
        $this->artisan('migrate:reset',['--database'=>'central_migrator','--force'=>true])->assertExitCode(0);
        self::assertSame(['migrations'],SchemaInspector::tables($db));
        $this->artisan('migrate',['--database'=>'central_migrator','--force'=>true])->assertExitCode(0);
        self::assertSame(array_keys($first),SchemaInspector::tables($db));
    }

    public function test_table_existence_matches_postgresql_visibility_types_and_immediate_privilege_changes(): void
    {
        $owner=DB::connection('central_migrator');$runtime=DB::connection('system');
        $role=PostgresBootstrap::identifier($runtime->getConfig('username'));
        $owner->statement('CREATE TABLE public.metadata_probe(id bigint, secret numeric(30,12))');
        $owner->statement('CREATE UNLOGGED TABLE public.metadata_unlogged(id integer)');
        $owner->statement('CREATE TABLE public.metadata_partitioned(id integer) PARTITION BY RANGE(id)');
        $owner->statement('CREATE TABLE public.metadata_leaf PARTITION OF public.metadata_partitioned FOR VALUES FROM(0) TO(100)');
        $owner->statement('CREATE VIEW public.metadata_view AS SELECT id FROM public.metadata_probe');
        $owner->statement('CREATE MATERIALIZED VIEW public.metadata_materialized AS SELECT id FROM public.metadata_probe');
        $owner->statement('CREATE TABLE public."MixedCase"(id integer)');
        $owner->statement('CREATE TEMP TABLE metadata_temporary(id integer)');
        $temp=$owner->selectOne('SELECT nspname FROM pg_catalog.pg_namespace WHERE oid=pg_my_temp_schema()')->nspname;
        $reference=(new \Illuminate\Database\Schema\Grammars\PostgresGrammar())->compileTableExists();
        foreach ([$owner,$runtime] as $db) {
            foreach (['metadata_probe'=>true,'metadata_unlogged'=>true,'metadata_partitioned'=>true,
                'metadata_leaf'=>true,'metadata_view'=>false,'metadata_materialized'=>false,
                'MixedCase'=>true,'metadata_missing'=>false,"missing' OR 1=1--"=>false] as $table=>$expected) {
                $actual=$db->getSchemaBuilder()->hasTable('public.'.$table);
                self::assertSame($expected,$actual,$db->getName().': '.$table);
                self::assertSame((bool)$db->select($reference,[$db->getDatabaseName(),'public',$table]),$actual);
            }
        }
        self::assertFalse($owner->getSchemaBuilder()->hasTable($temp.'.metadata_temporary'));
        self::assertSame([], $owner->select($reference,[$owner->getDatabaseName(),$temp,'metadata_temporary']));
        self::assertSame([], $owner->select($owner->getSchemaGrammar()->compileTableExists(),['other_database','public','metadata_probe']));
        $owner->statement('REVOKE ALL ON TABLE public.metadata_probe FROM '.$role);
        self::assertFalse($runtime->getSchemaBuilder()->hasTable('metadata_probe'));
        self::assertSame([], $runtime->select($reference,[$runtime->getDatabaseName(),'public','metadata_probe']));
        $owner->statement('GRANT SELECT(id) ON TABLE public.metadata_probe TO '.$role);
        self::assertTrue($runtime->getSchemaBuilder()->hasTable('metadata_probe'));
        self::assertNotEmpty($runtime->select($reference,[$runtime->getDatabaseName(),'public','metadata_probe']));
        $owner->statement('REVOKE SELECT(id) ON TABLE public.metadata_probe FROM '.$role);
        self::assertFalse($runtime->getSchemaBuilder()->hasTable('metadata_probe'));
        $owner->statement('GRANT DELETE ON TABLE public.metadata_probe TO '.$role);
        self::assertTrue($runtime->getSchemaBuilder()->hasTable('metadata_probe'));
        $owner->statement('DROP TABLE public."MixedCase"');
        self::assertFalse($runtime->getSchemaBuilder()->hasTable('public.MixedCase'));
    }
    public function test_restricted_provisioner_creates_isolates_renames_rotates_and_deletes(): void
    {
        $this->install();$service=app(TenantProvisioning::class);$a=$this->tenant('a');$b=$this->tenant('b');
        foreach ([$a,$b] as $c) $service->create($c,null,function () use ($c) {$db=$this->connect($c);$db->statement('CREATE TABLE probe(id bigserial PRIMARY KEY, amount numeric(30,12), snapshot json)');$db->table('probe')->insert(['amount'=>'0.300000000001','snapshot'=>'{"n":"1","d":"3"}']);});
        $db=$this->connect($a);self::assertSame('0.300000000001',$db->table('probe')->value('amount'));
        self::assertSame(1,$db->table('probe')->value('id'));
        try {$db->statement('CREATE DATABASE "'.$this->prefix.'_forbidden"');self::fail('Un tenant no debe crear bases.');} catch (\Illuminate\Database\QueryException $e) {self::assertSame('42501',$e->getCode());}
        try {$other=$b;$other['username']=$a['username'];$other['password']=$a['password'];$this->connect($other,'pg_cross')->getPdo();self::fail('No debe acceder a otra empresa.');} catch (\Throwable $e) {self::assertStringContainsString('permission denied',$e->getMessage());}
        try {$other=$this->owner;$other['username']=$a['username'];$other['password']=$a['password'];$this->connect($other,'pg_cross')->getPdo();self::fail('No debe acceder a central.');} catch (\Throwable $e) {self::assertStringContainsString('permission denied',$e->getMessage());}
        DB::purge('pg_tenant');DB::purge('pg_cross');
        $ledger=DB::connection('provisioning_ledger');$dispatcher=$ledger->getEventDispatcher();$injected=false;
        $ledger->setEventDispatcher(new \Illuminate\Events\Dispatcher(app()));
        $ledger->listen(function ($event) use (&$injected) {
            if (!$injected && str_starts_with($event->sql,'update "tenant_provisioning_runs"') && ($event->bindings[0] ?? null)==='ready') {
                $injected=true;throw new \RuntimeException('Injected rename publication failure');
            }
        });
        $failedRename=$this->tenant('rename_fault');
        try {$service->rename($a['database'],$failedRename,$a['password']);self::fail('Debe revertir el renombrado si falla su publicación.');}
        catch (\RuntimeException $error) {self::assertSame('Injected rename publication failure',$error->getMessage());}
        finally {$ledger->setEventDispatcher($dispatcher);}
        self::assertTrue($injected);self::assertSame(1,$this->connect($a)->table('probe')->count());DB::purge('pg_tenant');
        self::assertNull($this->control->selectOne('SELECT oid FROM pg_roles WHERE rolname=?',[$failedRename['database']]));
        self::assertNull($this->control->selectOne('SELECT oid FROM pg_database WHERE datname=?',[$failedRename['database']]));
        self::assertSame('compensated',$ledger->table('tenant_provisioning_runs')->where('tenant_name',$failedRename['database'])->value('state'));
        $renamed=$this->tenant('renamed');
        $active=$this->connect($a,'pg_rename_client');self::assertSame(1,$active->table('probe')->count());
        try {$service->rename($a['database'],$renamed,$a['password'],false);self::fail('Una activación debe respetar la conexión del cliente.');}
        catch (\Illuminate\Database\QueryException $error) {self::assertSame('55006',$error->getCode());}
        self::assertSame(1,$active->table('probe')->count());
        $service->rename($a['database'],$renamed,$a['password']);DB::purge('pg_rename_client');
        self::assertSame(1,$this->connect($renamed)->table('probe')->count());
        DB::purge('pg_tenant');$renamed['password']=bin2hex(random_bytes(24));$service->password($renamed['username'],$renamed['password']);
        self::assertSame(1,$this->connect($renamed)->table('probe')->count());DB::purge('pg_tenant');
        $service->delete($renamed['database']);$service->delete($b['database']);
        self::assertNull($this->control->selectOne('SELECT datname FROM pg_database WHERE datname=?',[$renamed['database']]));
    }
    public function test_failed_installation_is_compensated_and_existing_resources_are_preserved(): void
    {
        $this->install();$service=app(TenantProvisioning::class);$c=$this->tenant('fault');
        try {$service->create($c,null,static function () {throw new \RuntimeException('Injected seed failure');});self::fail('Debe fallar.');} catch (\RuntimeException $e) {self::assertSame('Injected seed failure',$e->getMessage());}
        self::assertNull($this->control->selectOne('SELECT oid FROM pg_roles WHERE rolname=?',[$c['database']]));
        self::assertNull($this->control->selectOne('SELECT oid FROM pg_database WHERE datname=?',[$c['database']]));
        self::assertSame('compensated',DB::connection('system')->table('tenant_provisioning_runs')->value('state'));
        $service->create($c,null,static function () {});
        try {$service->create($c,null,static function () {});self::fail('No debe reutilizar recursos existentes.');} catch (\LogicException $e) {self::assertStringContainsString('Ya existe',$e->getMessage());}
        self::assertNotNull($this->control->selectOne('SELECT oid FROM pg_database WHERE datname=?',[$c['database']]));
    }
    public function test_panel_onboarding_commits_central_and_tenant_data_and_compensates_failures(): void
    {
        $this->install();
        $this->artisan('db:seed',['--database'=>'central_migrator','--force'=>true])->assertExitCode(0);
        config(['tenant.prefix_database'=>$this->prefix,'tenant.app_url_base'=>'example.test','tenancy.website.cache'=>0]);
        $this->actingAs(\App\Models\System\User::first(),'admin');
        $request=\App\Http\Requests\System\ClientRequest::create('/clients','POST',[
            'name'=>'Empresa PostgreSQL temporal','email'=>'postgres-test@example.test','number'=>'J123456789',
            'password'=>'Fictional-test-password-17','subdomain'=>'panel','plan_id'=>1,'plan_period_id'=>1,'price'=>'99',
            'locked_emission'=>false,'enable_list_product'=>true,'type'=>'admin','fiscal_environment'=>'demo','fiscal_emission_mode'=>'free_form',
            'fiscal_configuration'=>[],'fiscal_credentials'=>null,'modules'=>[],'levels'=>[],
        ]);
        $result=app(\App\Http\Controllers\System\ClientController::class)->store($request);
        self::assertTrue($result['success'], $result['message'] ?? 'Alta fallida');
        $run=DB::connection('system')->table('tenant_provisioning_runs')->where('tenant_name',$this->prefix.'_panel')->first();
        self::assertSame('ready',$run->state);
        $website=\Hyn\Tenancy\Models\Website::where('uuid',$this->prefix.'_panel')->firstOrFail();
        $cfg=app(\Hyn\Tenancy\Database\Connection::class)->generateConfigurationArray($website);
        $tenant=$this->connect($cfg);
        self::assertSame('free_form',$tenant->table('companies')->value('fiscal_emission_mode'));
        self::assertSame('J123456789',$tenant->table('companies')->value('number'));
        self::assertGreaterThan(0,$tenant->table('users')->count());

        $apiRequest=\Illuminate\Http\Request::create('/api/tenant','POST',['ruc'=>'12345678901','subdomain'=>'api','email'=>'postgres-api@example.test','plan_id'=>1,'fiscal_environment'=>'demo','fiscal_emission_mode'=>'free_form','fiscal_configuration'=>[]]);
        $api=app(\App\Http\Controllers\System\Api\TenantController::class)->store($apiRequest);self::assertSame(200,$api->getStatusCode());
        $otherSite=\Hyn\Tenancy\Models\Website::where('uuid',$this->prefix.'_api')->firstOrFail();
        $otherConfig=app(\Hyn\Tenancy\Database\Connection::class)->generateConfigurationArray($otherSite);
        $other=$this->connect($otherConfig,'pg_other_tenant');self::assertSame(1,$other->table('companies')->value('id'));
        self::assertSame(1,$tenant->table('companies')->value('id'));self::assertNotSame($tenant->table('companies')->value('number'),$other->table('companies')->value('number'));
        $this->verifyHostnameIsolation($website,$cfg,$otherSite,$otherConfig);
        $failedName=$this->prefix.'_fault';
        try {
            \App\Tenancy\TenantOnboarding::run(function () use ($failedName) {
                $site=new \Hyn\Tenancy\Models\Website();$site->uuid=$failedName;
                app(\Hyn\Tenancy\Contracts\Repositories\WebsiteRepository::class)->create($site);
                app(\Hyn\Tenancy\Environment::class)->tenant($site);\App\Tenancy\TenantOnboarding::beginTenant();
                throw new \RuntimeException('Injected business initialization failure');
            });
            self::fail('Debe fallar el alta incompleta.');
        } catch (\RuntimeException $error) {self::assertSame('Injected business initialization failure',$error->getMessage());}
        self::assertSame(0,DB::connection('system')->table('websites')->where('uuid',$failedName)->count());
        self::assertNull($this->control->selectOne('SELECT oid FROM pg_database WHERE datname=?',[$failedName]));
        self::assertNull($this->control->selectOne('SELECT oid FROM pg_roles WHERE rolname=?',[$failedName]));
        self::assertSame('compensated',DB::connection('system')->table('tenant_provisioning_runs')->where('tenant_name',$failedName)->value('state'));
    }
    private function verifyHostnameIsolation($first,$firstConfig,$second,$secondConfig): void
    {
        $tokens=[];$companies=[];$hosts=[];$users=[];$clients=[];
        $central = DB::connection('system');
        foreach ([$first,$second] as $index=>$site) {
            $config=[$firstConfig,$secondConfig][$index];$db=$this->connect($config,'pg_http_prepare');$tokens[]=bin2hex(random_bytes(30));
            $user = $db->table('users')->orderBy('id')->first(['id', 'email']);
            $users[] = $user;
            $db->table('users')->where('id', $user->id)->update(['api_token'=>$tokens[$index],'email_verified_at'=>now()]);
            $companies[]=$db->table('companies')->value('number');$hosts[]=\Hyn\Tenancy\Models\Hostname::where('website_id',$site->id)->value('fqdn');
            $clients[] = $central->table('clients')->where('hostname_id', function ($query) use ($site) {
                $query->select('id')->from('hostnames')->where('website_id', $site->id);
            })->first(['id', 'number', 'name']);
        }
        $association = $central->table('multi_users')->insertGetId([
            'origin_client_id' => $clients[0]->id, 'origin_user_id' => $users[0]->id,
            'destination_client_id' => $clients[1]->id, 'destination_user_id' => $users[1]->id,
            'email' => $users[0]->email,
        ]);
        $directory=sys_get_temp_dir().'/sab-hostnames-'.bin2hex(random_bytes(6));mkdir($directory,0700);
        foreach (['framework/views','framework/cache','framework/sessions','logs'] as $path) mkdir($directory.'/'.$path,0700,true);
        $private=$directory.'/fixture.json';file_put_contents($private,json_encode(['central'=>config('database.connections.system'),'storage'=>$directory],JSON_THROW_ON_ERROR));chmod($private,0600);
        $socket=stream_socket_server('tcp://127.0.0.1:0');$address=stream_socket_get_name($socket,false);fclose($socket);
        $server=new \Symfony\Component\Process\Process([PHP_BINARY,'-S',$address,base_path('tests/Support/tenancy_http_router.php')],base_path(),['SAB_TENANCY_HTTP_FIXTURE'=>$private]);$server->setTimeout(120);$server->start();
        try {
            for ($i=0;$i<100;$i++) {$probe=@stream_socket_client('tcp://'.$address,$errno,$error,0.1);if ($probe) {fclose($probe);break;}usleep(50000);}
            $request = static function (string $path, int $host, int $token, int $expected) use ($address, $hosts, $tokens, $server): string {
                $curl=curl_init('http://'.$address.$path);curl_setopt_array($curl,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>20,CURLOPT_HTTPHEADER=>['Host: '.$hosts[$host],'Accept: application/json','Authorization: Bearer '.$tokens[$token]]]);
                $body=curl_exec($curl);$status=curl_getinfo($curl,CURLINFO_HTTP_CODE);curl_close($curl);self::assertSame($expected,$status,substr((string)$body,0,2000).substr($server->getErrorOutput(),0,1500));
                return (string) $body;
            };
            foreach ([[0,0,200],[1,1,200],[0,1,401],[1,0,401],[0,0,200]] as [$host,$token,$expected]) {
                $body = $request('/api/company', $host, $token, $expected);
                if ($expected===200) {self::assertStringContainsString($companies[$host],$body);self::assertStringNotContainsString($companies[1-$host],$body);}
            }
            $listing = '/api/multi-users/records';
            $own = json_decode($request($listing, 0, 0, 200), true, 512, JSON_THROW_ON_ERROR);
            self::assertEqualsCanonicalizing([
                ['id' => $association, 'is_destination' => true, 'client_full_name' => $clients[1]->number.' - '.$clients[1]->name, 'fqdn' => $hosts[1]],
                ['id' => null, 'is_destination' => true, 'client_full_name' => $clients[0]->number.' - '.$clients[0]->name, 'fqdn' => $hosts[0]],
            ], $own);
            $other = json_decode($request($listing, 1, 1, 200), true, 512, JSON_THROW_ON_ERROR);
            self::assertSame([$hosts[1]], array_column($other, 'fqdn'));
            self::assertSame([null], array_column($other, 'id'));
            $request($listing, 0, 1, 401);
        } finally {$server->stop();(new \Illuminate\Filesystem\Filesystem())->deleteDirectory($directory);DB::purge('pg_http_prepare');}
    }

    public function test_search_transport_native_dump_restore_and_generated_constraints(): void
    {
        $this->install();$service=app(TenantProvisioning::class);$c=$this->tenant('backup');
        $service->create($c,null,function () use ($c) {
            $db=$this->connect($c);$db->statement('CREATE TABLE probe (id bigserial PRIMARY KEY, name text COLLATE "sab_ci_ai" UNIQUE, active boolean NOT NULL DEFAULT false, amount numeric(30,12), rate numeric(18,8), payload json, active_id bigint GENERATED ALWAYS AS (CASE WHEN active THEN id ELSE NULL END) STORED UNIQUE)');
            $db->table('probe')->insert(['id'=>42,'name'=>'José Ñandú','active'=>true,'amount'=>'999999999999999999.123456789012','rate'=>'123.12345678','payload'=>'{"amount":0.123456789012,"fraction":{"n":"1","d":"3"}}']);SequenceSynchronizer::sync($db);
        });
        $db=$this->connect($c);$row=$db->table('probe')->where('name','like','%JOSE NANDU%')->first();
        self::assertSame(1,$row->active);self::assertSame('999999999999999999.123456789012',$row->amount);self::assertStringContainsString('0.123456789012',$row->payload);
        self::assertSame(43,$db->table('probe')->insertGetId(['name'=>'Otro']));
        $file=tempnam(sys_get_temp_dir(),'pg17-dump-');$backup=new PostgresBackup();$backup->dump($c,$file);
        $target=$this->tenant('restored');
        try {$service->create($target,null,static function () use ($backup,$target,$file) {$backup->restore($target,$file);},false);
            $restored=$this->connect($target,'pg_restored');self::assertSame('123.12345678',$restored->table('probe')->where('id',42)->value('rate'));
            self::assertSame(44,$restored->table('probe')->insertGetId(['name'=>'Restaurado']));
            self::assertCount(3,SchemaInspector::indexes($restored,'probe'));
        } finally {unlink($file);}
    }
    public function test_update_timestamp_search_unsigned_range_and_json_are_preserved(): void
    {
        $db=DB::connection('central_migrator');PostgresBootstrap::install($db);
        $db->statement('CREATE TABLE audit_probe(id bigserial PRIMARY KEY,name text COLLATE "sab_ci_ai", payload json, created_at timestamp without time zone NOT NULL DEFAULT LOCALTIMESTAMP, counter numeric(20,0) CHECK(counter BETWEEN 0 AND 18446744073709551615))');
        PostgresBootstrap::installTouchTrigger($db,'audit_probe','created_at');
        self::assertSame(['id','name','payload','created_at','counter'],$db->getSchemaBuilder()->getColumnListing('public.audit_probe'));
        self::assertTrue($db->getSchemaBuilder()->hasColumn('public.audit_probe','payload'));
        self::assertFalse($db->getSchemaBuilder()->hasColumn('public.audit_probe','missing'));
        $runtime=DB::connection('system');
        self::assertSame(['id','name','payload','created_at','counter'],$runtime->getSchemaBuilder()->getColumnListing('public.audit_probe'));
        $db->statement('CREATE TABLE owner_only_probe(id bigint)');
        $db->statement('REVOKE ALL ON owner_only_probe FROM '.PostgresBootstrap::identifier(config('database.connections.system.username')));
        self::assertSame([],$runtime->getSchemaBuilder()->getColumnListing('public.owner_only_probe'));
        $db->statement('ALTER TABLE owner_only_probe ADD COLUMN extra text');
        self::assertSame(['id','extra'],$db->getSchemaBuilder()->getColumnListing('public.owner_only_probe'));
        $db->statement('ALTER TABLE owner_only_probe DROP COLUMN extra');
        self::assertSame(['id'],$db->getSchemaBuilder()->getColumnListing('public.owner_only_probe'));
        $json='{"decimal": 0.123456789012, "ordered": [1, 2], "n": "1", "d": "3"}';
        $db->table('audit_probe')->insert(['name'=>'  José Ñandú','created_at'=>'2000-01-01 00:00:00','payload'=>$json,'counter'=>'18446744073709551615']);
        self::assertSame(1,$db->table('audit_probe')->where('name','like','%JOSE NANDU%')->count());
        self::assertSame(1,$db->table('audit_probe')->where('name','like','%JOSE N_NDU%')->count());
        $transport=$db->selectOne("SELECT '!'::char(2) AS code, 'name '::varchar(6) AS name, '{\"d\":0.123456789012} '::json AS payload");
        self::assertSame('!',$transport->code);self::assertSame('name ',$transport->name);self::assertSame('{"d":0.123456789012} ',$transport->payload);
        $db->statement('UPDATE audit_probe SET name=name');
        self::assertSame('2000-01-01 00:00:00',$db->table('audit_probe')->value('created_at'));
        $db->statement('UPDATE audit_probe SET name=?,created_at=created_at',['Otro']);
        self::assertSame('2000-01-01 00:00:00',$db->table('audit_probe')->value('created_at'));
        $db->statement('UPDATE audit_probe SET name=?',['Actualizado']);
        self::assertNotSame('2000-01-01 00:00:00',$db->table('audit_probe')->value('created_at'));
        self::assertSame($json,$db->table('audit_probe')->value('payload'));
        self::assertSame('18446744073709551615',$db->table('audit_probe')->value('counter'));
        $db->getSchemaBuilder()->create('timestamp_probe',function ($table) {$table->bigIncrements('id');$table->timestamp('created_at')->useCurrent();});
        $db->transaction(function () use ($db) {
            $db->table('timestamp_probe')->insert(['id'=>1]);$db->table('audit_probe')->update(['name'=>'Primera sentencia']);
            $first=$db->table('timestamp_probe')->where('id',1)->value('created_at');$firstUpdate=$db->table('audit_probe')->value('created_at');
            $db->select('SELECT pg_sleep(1.1)');
            $db->table('timestamp_probe')->insert(['id'=>2]);$db->table('audit_probe')->update(['name'=>'Segunda sentencia']);
            self::assertNotSame($first,$db->table('timestamp_probe')->where('id',2)->value('created_at'));
            self::assertNotSame($firstUpdate,$db->table('audit_probe')->value('created_at'));
        });
        try {$db->table('audit_probe')->update(['counter'=>'18446744073709551616']);self::fail('Debe respetar el rango original.');}
        catch (\Illuminate\Database\QueryException $e) {self::assertSame('23514',$e->getCode());}
        foreach ([new \App\Models\Tenant\Document(),new \App\Models\Tenant\Dispatch()] as $model) {
            $model->setRawAttributes(['number'=>'102']);self::assertSame(102,$model->number);
            $model->setRawAttributes(['number'=>'18446744073709551615']);self::assertSame('18446744073709551615',$model->number);
        }
    }
    public function test_integral_backup_restores_central_two_tenants_files_permissions_and_sequences(): void
    {
        $this->install();$service=app(TenantProvisioning::class);$configs=[];$names=[];$sites=[];$schemas=[];$data=[];
        config(['tenant.prefix_database'=>$this->prefix,'tenant.app_url_base'=>'example.test','tenancy.website.cache'=>0]);
        $this->actingAs(\App\Models\System\User::first(),'admin');
        $fingerprint=static function ($db): array {
            $result=[];
            foreach (SchemaInspector::tables($db) as $table) {
                $rows=array_map(static fn ($row)=>json_encode((array)$row,JSON_THROW_ON_ERROR),$db->table($table)->get()->all());
                sort($rows,SORT_STRING);$result[$table]=hash('sha256',json_encode($rows,JSON_THROW_ON_ERROR));
            }
            return $result;
        };
        $root=storage_path('app/sab-native-backup-'.$this->prefix);mkdir($root,0700,true);
        $fileDirectories=[];
        try {
            foreach (['one','two'] as $suffix) {
                $request=\App\Http\Requests\System\ClientRequest::create('/clients','POST',[
                    'name'=>'Empresa integral '.$suffix,'email'=>$suffix.'@backup.example.test','number'=>$suffix==='one' ? 'J123456789' : 'J987654321',
                    'password'=>'Fictional-test-password-17','subdomain'=>$suffix,'plan_id'=>1,'plan_period_id'=>1,'price'=>'99',
                    'locked_emission'=>false,'enable_list_product'=>true,'type'=>'admin','fiscal_environment'=>'demo','fiscal_emission_mode'=>'free_form',
                    'fiscal_configuration'=>[],'fiscal_credentials'=>null,'modules'=>[],'levels'=>[],
                ]);
                $created=app(\App\Http\Controllers\System\ClientController::class)->store($request);
                self::assertTrue($created['success'],$created['message'] ?? 'Alta integral fallida');
                $site=\Hyn\Tenancy\Models\Website::where('uuid',$this->prefix.'_'.$suffix)->firstOrFail();$sites[]=$site;
                $c=app(\Hyn\Tenancy\Database\Connection::class)->generateConfigurationArray($site);$configs[]=$c;$names[]=$c['database'];
                $db=$this->connect($c);$db->statement('CREATE TABLE amounts(id bigserial PRIMARY KEY,company_id bigint REFERENCES companies(id),amount numeric(30,12),snapshot json)');
                $db->table('amounts')->insert(['id'=>40,'company_id'=>1,'amount'=>'0.300000000001','snapshot'=>'{"n":"1","d":"3"}']);SequenceSynchronizer::sync($db);
                $schemas[$c['database']]=SchemaInspector::snapshot($db);$data[$c['database']]=$fingerprint($db);
                self::assertGreaterThanOrEqual(350,count($schemas[$c['database']]));
                $directory=storage_path('app/tenancy/tenants/'.$c['database']);if (!is_dir($directory)) mkdir($directory,0700,true);$fileDirectories[]=$directory;
                file_put_contents($directory.'/private.txt',$c['database']);
            }
            $shared=storage_path('app/public/uploads/'.$this->prefix);mkdir($shared,0700,true);$fileDirectories[]=$shared;file_put_contents($shared.'/file.txt','shared temporary file');
            // Capture the actual central cut before restores add their own provisioning ledger rows.
            $centralData=$fingerprint(DB::connection('central_migrator'));
            $centralSchema=SchemaInspector::snapshot(DB::connection('central_migrator'));
            $centralMaximumPlan=DB::connection('central_migrator')->table('plans')->max('id');
            foreach (DB::getConnections() as $name=>$db) if ($name!=='pg_control') $db->disconnect();
            $package=$root.'/integral.zip';
            $databases=array_merge([$this->owner['database']],$names);
            $manager=app(PostgresBackupManager::class);
            $temporaryDumps=glob(sys_get_temp_dir().'/sab-pgdump-*');
            $assertCutRejected=static function (string $message) use ($manager,$databases,$package,$temporaryDumps): void {
                try {
                    $manager->backup($databases,$package,true);
                    self::fail('El respaldo no debe completar un corte integral inválido.');
                } catch (\LogicException $error) {
                    self::assertStringContainsString($message,$error->getMessage());
                }
                self::assertFileDoesNotExist($package);
                self::assertSame($temporaryDumps,glob(sys_get_temp_dir().'/sab-pgdump-*'));
            };
            self::assertFalse(app()->isDownForMaintenance());
            $assertCutRejected('requiere mantenimiento');
            $this->artisan('down')->assertExitCode(0);

            $system = DB::connection('system');
            $system->beginTransaction();
            try {
                $this->artisan('bk:bd', ['type' => 'todos', '--quiesced' => true])->run();
                self::fail('El corte debe rechazar una transacción central propia abierta.');
            } catch (\LogicException $error) {
                self::assertStringContainsString('Cierre la transacción central', $error->getMessage());
                self::assertTrue($system->getPdo()->inTransaction());
            } finally {
                $system->rollBack();
                $system->disconnect();
            }

            // The last tenant still has uncommitted writes while the first two dumps finish.
            $writer=$this->connect($configs[1],'pg_backup_writer');
            $writer->beginTransaction();
            try {
                $writer->table('amounts')->where('id',40)->update(['amount'=>'0.9']);
                $writerPid=$writer->selectOne('SELECT pg_backend_pid() AS pid')->pid;
                $session=$this->control->selectOne('SELECT datname,state FROM pg_stat_activity WHERE pid=?',[$writerPid]);
                self::assertSame($names[1],$session->datname);
                self::assertSame('idle in transaction',$session->state);
                $assertCutRejected('Existen operaciones activas');
            } finally {
                $writer->rollBack();
                DB::purge('pg_backup_writer');
            }
            foreach (DB::getConnections() as $name=>$db) if ($name!=='pg_control') $db->disconnect();
            $manifest=$manager->backup($databases,$package,true);
            self::assertTrue($manifest['global_coherent']);self::assertCount(3,$manifest['databases']);
            self::assertSame($temporaryDumps,glob(sys_get_temp_dir().'/sab-pgdump-*'));
            foreach ($configs as $source) {
                $result=app(\App\Tenancy\TenantRestore::class)->restore($source['database'],$package,$source['database']);
                self::assertFalse($result['activated']);$fileDirectories[]=$result['files'];
                self::assertSame($source['database'],file_get_contents($result['files'].'/tenant/private.txt'));
                self::assertSame('shared temporary file',file_get_contents($result['files'].'/uploads/'.$this->prefix.'/file.txt'));
                // Provisioner membership permits a separate validation connection without exposing a staging password.
                $c=config('database.connections.provisioner');$c['database']=$result['database'];$db=$this->connect($c,'pg_validate_restore');
                self::assertSame($schemas[$source['database']],SchemaInspector::snapshot($db),'Esquema completo de la empresa restaurada');
                self::assertSame($data[$source['database']],$fingerprint($db),'Datos de todas las tablas restauradas');
                self::assertSame('0.300000000001',$db->table('amounts')->value('amount'));
                self::assertSame('{"n":"1","d":"3"}',$db->table('amounts')->value('snapshot'));
                self::assertSame(41,$db->table('amounts')->insertGetId(['company_id'=>1,'amount'=>'0.1']));
                self::assertCount(1,SchemaInspector::foreignKeys($db,'amounts'));DB::purge('pg_validate_restore');
            }
            $backup=new PostgresBackup();$backup->extract($package,'databases/',$root.'/dump');$centralCopy=$this->tenant('central_copy');
            $service->create($centralCopy,null,function () use ($centralCopy,$package) {
                $before=config('database.connections.central_migrator');
                config(['database.connections.central_migrator'=>$centralCopy]);DB::purge('central_migrator');
                try {$this->artisan('sab:restore-central',['package'=>$package,'--source'=>$this->owner['database']])->assertExitCode(0);}
                finally {config(['database.connections.central_migrator'=>$before]);DB::purge('central_migrator');}
            },false);
            $copy=$this->connect($centralCopy,'pg_central_copy');self::assertSame(2,$copy->table('websites')->count());self::assertSame(2,$copy->table('plans')->count());
            self::assertSame($centralSchema,SchemaInspector::snapshot($copy));
            self::assertSame($centralData,$fingerprint($copy),'Datos de todas las tablas centrales en el corte restaurado');
            $sequenceColumns=$copy->select("SELECT table_name,column_name FROM information_schema.columns WHERE table_schema='public' AND (column_default LIKE 'nextval(%' OR is_identity='YES') ORDER BY table_name,column_name");
            self::assertNotEmpty($sequenceColumns);
            foreach ($sequenceColumns as $column) {
                $table=PostgresBootstrap::identifier($column->table_name);
                $field=PostgresBootstrap::identifier($column->column_name);
                $sequence=$copy->selectOne('SELECT pg_get_serial_sequence(?,?) AS name',['public.'.$table,$column->column_name])->name;
                self::assertNotNull($sequence);
                $parameters=$copy->selectOne('SELECT (seqincrement>0 AND seqstart>0 AND NOT seqcycle)::int AS ascending FROM pg_sequence WHERE seqrelid=?::regclass',[$sequence]);
                self::assertSame(1,(int)$parameters->ascending,'Secuencia central ascendente sin ciclo '.$sequence);
                $next=$copy->selectOne('SELECT (nextval(?::regclass)>COALESCE((SELECT MAX('.$field.') FROM public.'.$table.'),0))::int AS clear',[$sequence]);
                self::assertSame(1,(int)$next->clear,'Secuencia central '.$column->table_name.'.'.$column->column_name);
            }
            $plan=(array)$copy->table('plans')->orderBy('id')->first();unset($plan['id']);$plan['name']='Plan temporal restaurado';
            self::assertGreaterThan($centralMaximumPlan,$copy->table('plans')->insertGetId($plan));
            foreach (DB::getConnections() as $name=>$db) if ($name!=='pg_control') $db->disconnect();
            $source=$configs[0];$changed=$this->connect($source,'pg_activation');$changed->table('amounts')->where('id',40)->update(['amount'=>'0.9']);DB::purge('pg_activation');
            $active=app(\App\Tenancy\TenantRestore::class)->restore($source['database'],$package,$source['database'],true);
            self::assertTrue($active['activated']);
            clearstatcache();self::assertSame(0644,fileperms($shared.'/file.txt') & 0777);
            self::assertSame(0600,fileperms(storage_path('app/tenancy/tenants/'.$source['database'].'/private.txt')) & 0777);$fileDirectories[]=$active['previous_files'];
            $fileDirectories[]=dirname($active['shared_files_recovery']);
            self::assertSame('0.300000000001',$this->connect($source,'pg_activation')->table('amounts')->where('id',40)->value('amount'));
            DB::connection('pg_activation')->table('amounts')->where('id',40)->update(['amount'=>'0.9']);DB::purge('pg_activation');
            unlink($shared.'/file.txt');symlink($root.'/target-does-not-exist',$shared.'/file.txt');
            try {app(\App\Tenancy\TenantRestore::class)->restore($source['database'],$package,$source['database'],true);self::fail('Debe deshacer la activación incompleta.');}
            catch (\LogicException $error) {self::assertStringContainsString('simbólicos',$error->getMessage());}
            self::assertSame('0.900000000000',$this->connect($source,'pg_activation')->table('amounts')->where('id',40)->value('amount'));
            self::assertSame($source['database'],file_get_contents(storage_path('app/tenancy/tenants/'.$source['database'].'/private.txt')));
            $invalid=$root.'/invalid.zip';copy($package,$invalid);$zip=new \ZipArchive();$zip->open($invalid);$zip->addFromString('databases/'.$names[0].'.dump','truncated');$zip->close();
            try {$backup->verify($invalid);self::fail('No debe aceptar archivos alterados.');}catch (\InvalidArgumentException $e) {self::assertStringContainsString('checksum',$e->getMessage());}
            $this->artisan('up')->assertExitCode(0);
            $this->verifyHostnameIsolation($sites[0],$configs[0],$sites[1],$configs[1]);
        } finally {
            $this->artisan('up')->run();
            foreach ($fileDirectories as $directory) (new \Illuminate\Filesystem\Filesystem())->deleteDirectory($directory);
            (new \Illuminate\Filesystem\Filesystem())->deleteDirectory($root);
        }
    }

    public function test_pg_dump_disk_full_keeps_source_data_and_removes_incomplete_dump(): void
    {
        $mount='/sab-backup-faults';
        self::assertDirectoryExists($mount,'Use the isolated test Compose tmpfs.');
        $stat=new \Symfony\Component\Process\Process(['stat','-f','--format=%T %S %b',$mount]);$stat->mustRun();
        [$type,$blockSize,$blocks]=explode(' ',trim($stat->getOutput()));
        self::assertSame('tmpfs',$type);
        self::assertGreaterThanOrEqual(524288,(int)$blockSize*(int)$blocks);
        self::assertLessThanOrEqual(2*1024*1024,(int)$blockSize*(int)$blocks);
        self::assertSame([],array_values(array_diff(scandir($mount),['.','..'])));
        $directory=$mount.'/dump-'.bin2hex(random_bytes(8));self::assertTrue(mkdir($directory,0700));
        try {
            $db=DB::connection('central_migrator');$db->statement('CREATE TABLE backup_full_probe(payload text NOT NULL)');
            $payload=base64_encode(random_bytes(3*1024*1024));
            $db->table('backup_full_probe')->insert(['payload'=>$payload]);$checksum=hash('sha256',$payload);unset($payload);
            try {(new PostgresBackup())->dump($this->owner,$directory.'/database.dump');self::fail('pg_dump must fail on the private full tmpfs.');}
            catch (\RuntimeException $error) {self::assertStringContainsString('No space left on device',$error->getMessage());}
            self::assertSame([],array_values(array_diff(scandir($directory),['.','..'])));
            self::assertSame(1,$db->table('backup_full_probe')->count());
            self::assertSame($checksum,hash('sha256',$db->table('backup_full_probe')->value('payload')));
        } finally {(new \Illuminate\Filesystem\Filesystem())->deleteDirectory($directory);}
    }

    public function test_repeatable_read_retry_limit_and_savepoint_recover_real_postgresql_errors(): void
    {
        $a=DB::connection('central_migrator');$a->statement('CREATE TABLE concurrency_probe(id integer PRIMARY KEY,value integer NOT NULL)');
        $a->table('concurrency_probe')->insert(['id'=>1,'value'=>0]);$b=$this->connect($this->owner,'pg_other');
        \App\Support\Exact\ReadSnapshot::run($a,function () use ($a,$b) {
            self::assertSame('repeatable read',$a->selectOne('SHOW transaction_isolation')->transaction_isolation);
            self::assertSame(0,$a->table('concurrency_probe')->value('value'));$b->table('concurrency_probe')->increment('value');
            self::assertSame(0,$a->table('concurrency_probe')->value('value'));
        });
        self::assertSame('read committed',$a->selectOne('SHOW transaction_isolation')->transaction_isolation);
        $attempts=0;
        $result=\App\Database\SqlErrors::transaction($a,function () use ($a,$b,&$attempts) {
            $attempts++;$a->statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$value=$a->table('concurrency_probe')->value('value');
            if ($attempts===1) $b->table('concurrency_probe')->increment('value');
            $a->table('concurrency_probe')->where('id',1)->update(['value'=>$value+1]);return $value;
        });
        self::assertSame(2,$attempts);self::assertSame(2,$result);self::assertSame(3,$a->table('concurrency_probe')->value('value'));
        $attempts=0;
        try {\App\Database\SqlErrors::transaction($a,function () use ($a,$b,&$attempts) {
            $attempts++;$a->statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$a->table('concurrency_probe')->value('value');
            $b->table('concurrency_probe')->increment('value');$a->table('concurrency_probe')->increment('value');
        });self::fail('Debe agotar tres intentos.');}catch (\Throwable $error) {self::assertSame('40001',\App\Database\SqlErrors::state($error));}
        self::assertSame(3,$attempts);self::assertSame(0,$a->transactionLevel());
        $a->transaction(function () use ($a) {
            try {$a->transaction(fn ()=>$a->table('concurrency_probe')->insert(['id'=>1,'value'=>9]));self::fail('Debe rechazar un duplicado.');}
            catch (\Illuminate\Database\QueryException $error) {self::assertSame('23505',$error->getCode());}
            self::assertSame(1,$a->table('concurrency_probe')->count());
        });
    }

    public function test_worker_switches_tenants_releases_credentials_and_restores_sync_context(): void
    {
        $this->install();$service=app(TenantProvisioning::class);$sites=[];
        foreach (['job_a','job_b'] as $suffix) {
            $site=\Hyn\Tenancy\Models\Website::withoutEvents(function () use ($suffix) {$site=new \Hyn\Tenancy\Models\Website();$site->uuid=$this->prefix.'_'.$suffix;$site->save();return $site;});
            $c=app(\Hyn\Tenancy\Database\Connection::class)->generateConfigurationArray($site);
            $service->create($c,null,function () use ($c) {$db=$this->connect($c);$db->statement('CREATE TABLE companies(id integer PRIMARY KEY,name text)');$db->table('companies')->insert(['id'=>1,'name'=>$c['database']]);});$sites[]=$site;
        }
        $environment=app(\Hyn\Tenancy\Environment::class);$events=app('events');\App\Tenancy\TenantJobContext::clear();
        foreach ($sites as $site) {
            $job=$this->createMock(\Illuminate\Contracts\Queue\Job::class);$job->method('payload')->willReturn(['website_id'=>$site->id]);
            $events->dispatch(new \Illuminate\Queue\Events\JobProcessing('temporary',$job));
            self::assertSame($site->uuid,DB::connection('tenant')->table('companies')->where('id',1)->value('name'));
            self::assertSame($site->uuid,DB::connection('tenant')->getConfig('username'));
            $events->dispatch(new \Illuminate\Queue\Events\JobProcessed('temporary',$job));
            self::assertNull($environment->tenant());self::assertSame([],config('database.connections.tenant'));
        }

        config(['queue.connections.database.connection'=>'system']);
        $queue=app('queue')->connection('database');
        $worker=app('queue.worker');
        foreach ($sites as $site) {
            $queue->push(new TenantQueueWriteFixture($site->id,'worker:'.$site->uuid), '', 'tenant-isolation');
        }
        foreach ($sites as $site) {
            $worker->runNextJob('database','tenant-isolation',new \Illuminate\Queue\WorkerOptions(sleep: 0));
            self::assertNull($environment->tenant());
            self::assertSame([],config('database.connections.tenant'));
        }
        self::assertSame(0,$queue->size('tenant-isolation'));
        foreach ($sites as $site) {
            $config=app(\Hyn\Tenancy\Database\Connection::class)->generateConfigurationArray($site);
            self::assertSame('worker:'.$site->uuid,$this->connect($config)->table('companies')->where('id',1)->value('name'));
        }

        $environment->tenant($sites[0]);$job=$this->createMock(\Illuminate\Contracts\Queue\Job::class);$job->method('payload')->willReturn(['website_id'=>$sites[1]->id]);
        $events->dispatch(new \Illuminate\Queue\Events\JobProcessing('temporary',$job));
        $events->dispatch(new \Illuminate\Queue\Events\JobExceptionOccurred('temporary',$job,new \RuntimeException('Injected worker failure')));
        self::assertSame($sites[0]->uuid,DB::connection('tenant')->getDatabaseName());
        \App\Tenancy\TenantJobContext::clear();
        $job=$this->createMock(\Illuminate\Contracts\Queue\Job::class);$job->method('payload')->willReturn([]);
        $events->dispatch(new \Illuminate\Queue\Events\JobProcessing('temporary',$job));self::assertSame([],config('database.connections.tenant'));
        $events->dispatch(new \Illuminate\Queue\Events\JobProcessed('temporary',$job));
    }

    public function test_legacy_delimited_import_is_atomic_and_requires_an_authenticated_admin(): void
    {
        $db=DB::connection('central_migrator');PostgresBootstrap::install($db);DB::setDefaultConnection('central_migrator');
        $migration=require glob(database_path('migrations/tenant/*_create_padrones_table.php'))[0];$migration->up();
        $file=tempnam(sys_get_temp_dir(),'sab-padron-');
        $header='RUC|NOMBRE|ESTADO|CONDICION|UBIGEO|TIPO_VIA|NOMBRE_VIA|CODIGO_ZONA|TIPO_ZONA|NUMERO|INTERIOR|LOTE|DEPARTAMENTO|MANZANA|KILOMETRO';
        $row=['00000000001','José sintético','ACTIVO','LOCAL','000001','CALLE','Prueba','01','ZONA','1','','','Estado','',''];
        $service=new \Modules\Padron\Services\DelimitedPadronImporter();
        try {
            file_put_contents($file,$header."\n".implode('|',$row)."\n");self::assertSame(1,$service->import($db,$file));
            self::assertSame('00000000001',$db->table('padrones')->value('ruc'));self::assertSame('Prueba',$db->table('padrones')->value('nombre_via'));
            file_put_contents($file,$header."\n".implode('|',$row)."\nincomplete|row\n");
            try {$service->import($db,$file);self::fail('Debe fallar sin borrar el catálogo anterior.');}catch (\InvalidArgumentException $error) {self::assertStringContainsString('incompleta',$error->getMessage());}
            self::assertSame(1,$db->table('padrones')->count());self::assertSame('José sintético',$db->table('padrones')->value('nombre_razon_social'));
            try {app('router')->getRoutes()->match(\Illuminate\Http\Request::create('http://example.test/padron/demo','GET'));self::fail('GET no debe iniciar una importación.');}
            catch (\Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException $error) {self::assertSame(405,$error->getStatusCode());self::assertStringContainsString('POST',$error->getHeaders()['Allow']);}
            try {app(\Modules\Padron\Http\Controllers\PadronController::class)->charges_data();self::fail('Un anónimo no debe iniciar una descarga ni vaciar datos.');}
            catch (\Symfony\Component\HttpKernel\Exception\HttpException $error) {self::assertSame(403,$error->getStatusCode());}
        } finally {unlink($file);DB::setDefaultConnection('system');}
    }

}

final class TenantQueueWriteFixture
{
    public function __construct(public int $website_id, private string $name)
    {
    }

    public function handle(): void
    {
        DB::connection('tenant')->table('companies')->where('id',1)->update(['name'=>$this->name]);
    }
}
// ######### FIN CAMBIO POSTGRESQL 17
