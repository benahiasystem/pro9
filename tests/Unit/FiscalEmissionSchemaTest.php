<?php

namespace Tests\Unit;

use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager;
use Illuminate\Database\Connection;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Facade;
use PHPUnit\Framework\TestCase;

// ######## INICIO MODALIDAD DE EMISIÓN FISCAL ########
/** @group postgres-integration */
class FiscalEmissionSchemaTest extends TestCase
{
    use \Tests\Support\DispatchPersistenceCases;
    use \Tests\Support\NotePersistenceCases;
    use \Tests\Support\CreditNumberingPersistenceCases;
    use \Tests\Support\CreditInvoicePersistenceCases;
    use \Tests\Support\SpePersistenceCases;
    // ######## INICIO RETIRADA DE EXCEPCION ADMINISTRATIVA IGTF ########
    use \Tests\Support\IgtfRetirementPersistenceCases;
    // ######## FIN RETIRADA DE EXCEPCION ADMINISTRATIVA IGTF ########
    use \Tests\Support\IvaRetentionPersistenceCases;
    use \Tests\Support\IslrRetentionPersistenceCases;
    use \Tests\Support\HkaAutoSendPersistenceCases;
    use \Tests\Support\PaymentClassificationPersistenceCases;
    private function invoiceTestCurrencies(): array
    {
        return getenv('SAB_TEST_INVOICES_VES_ONLY') === '1' || \App\Services\SalesDocumentCurrency::usesBolivars()
            ? ['VES'] : ['VES', 'USD'];
    }

    private static ?\WeakMap $globalInventoryDispatchers=null;
    private $previousContainer;
    private $previousFacade;
    private $capsule;
    private $database;
    private $previousResolver;
    private $previousDispatcher;
    private array $previousFrameworkState=[];

    protected function setUp(): void
    {
        if (getenv('SAB_POSTGRES_TESTS') !== '1') {
            $this->markTestSkipped('Active SAB_POSTGRES_TESTS=1 para usar una base PostgreSQL temporal aislada.');
        }
        $this->previousContainer = Container::getInstance();
        $this->previousFacade = Facade::getFacadeApplication();
        $this->previousResolver = \Illuminate\Database\Eloquent\Model::getConnectionResolver();
        $this->previousDispatcher = \Illuminate\Database\Eloquent\Model::getEventDispatcher();
        \Illuminate\Database\Eloquent\Model::clearBootedModels();
        // Rendering tests may have cached a reduced table definition for the same model.
        $guardableColumns = new \ReflectionProperty(\Illuminate\Database\Eloquent\Model::class, 'guardableColumns');
        $guardableColumns->setAccessible(true);
        $this->previousFrameworkState[] = [$guardableColumns, $guardableColumns->getValue()];
        $guardableColumns->setValue(null, []);
        $root = dirname(__DIR__, 2);
        $app = new Application($root);
        Container::setInstance($app);
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($app);
        $app->instance('request', \Illuminate\Http\Request::create('http://fiscal-schema.example.test'));
        foreach ([\Illuminate\Pagination\Paginator::class=>['viewFactoryResolver','currentPathResolver','currentPageResolver','queryStringResolver'],
            \Illuminate\Pagination\CursorPaginator::class=>['currentCursorResolver']] as $class=>$properties) {
            foreach ($properties as $property) {
                $reflection=new \ReflectionProperty($class,$property);$reflection->setAccessible(true);
                $this->previousFrameworkState[]=[$reflection,$reflection->getValue()];
            }
        }
        \Illuminate\Pagination\PaginationState::resolveUsing($app);
        $guard = $this->createMock(\Illuminate\Contracts\Auth\Guard::class);
        $guard->method('check')->willReturn(false);
        $auth = $this->createMock(\Illuminate\Contracts\Auth\Factory::class);
        $auth->method('guard')->willReturn($guard);
        $app->instance('auth', $auth);
        if (!class_exists('DB')) {
            class_alias(\Illuminate\Support\Facades\DB::class, 'DB');
        }
        $app->instance('config', new Repository([
            'app' => ['locale' => 'es', 'fallback_locale' => 'es', 'timezone' => 'America/Caracas'],
            'fiscal_emission' => require $root . '/config/fiscal_emission.php',
            'venezuela' => require $root . '/config/venezuela.php',
        ]));
        $app->instance('validator', new \Illuminate\Validation\Factory(
            new \Illuminate\Translation\Translator(new \Illuminate\Translation\ArrayLoader(), 'es'),
            $app
        ));
        $connection = \Tests\Support\PostgresTestEnvironment::connection();
        $this->capsule = new Manager($app);
        $this->capsule->addConnection($connection, 'control');
        \Tests\Support\PostgresTestEnvironment::assertServer($this->capsule->getConnection('control'));
        $this->database = 'sab_fiscal_test_' . bin2hex(random_bytes(6));
        $this->capsule->getConnection('control')->statement('CREATE DATABASE "' . $this->database . '" TEMPLATE template0');
        \Tests\Support\FiscalTemporaryDatabases::register($this->database, $this->capsule->getConnection('control')->getPdo());
        $connection['database'] = $this->database;
        config(['filesystems.disks.tenant' => ['driver' => 'local', 'root' => sys_get_temp_dir().'/'.$this->database]]);
        $app->instance('filesystem', new \Illuminate\Filesystem\FilesystemManager($app));
        $this->capsule->addConnection($connection, 'tenant');
        $this->capsule->addConnection($connection, 'system');
        $this->capsule->getDatabaseManager()->setDefaultConnection('tenant');
        $this->capsule->getConnection('tenant')->setTransactionManager(new \Illuminate\Database\DatabaseTransactionsManager());
        $app->instance('db', $this->capsule->getDatabaseManager());
        $app->instance('cache', new \Illuminate\Cache\Repository(new \Illuminate\Cache\ArrayStore()));
        $app->bind('db.schema', fn () => $this->capsule->getConnection()->getSchemaBuilder());
        $tenancy = $this->getMockBuilder(\App\Tenancy\Database\Connection::class)->disableOriginalConstructor()->onlyMethods(['tenantName', 'systemName'])->getMock();
        $tenancy->method('tenantName')->willReturn('tenant');
        $tenancy->method('systemName')->willReturn('system');
        $app->instance(\App\Tenancy\Database\Connection::class, $tenancy);
        $this->capsule->bootEloquent();
        $app->make('validator')->setPresenceVerifier(
            new \Illuminate\Validation\DatabasePresenceVerifier($this->capsule->getDatabaseManager())
        );
    }

    protected function tearDown(): void
    {
        try {
            if ($this->database && preg_match('/^sab_fiscal_test_[a-f0-9]{12}$/', $this->database)) {
                $tenant = $this->capsule->getConnection('tenant');
                while ($tenant->transactionLevel() > 0) $tenant->rollBack();
                $this->capsule->getConnection('control')->statement('DROP DATABASE "' . $this->database . '" WITH (FORCE)');
                \Tests\Support\FiscalTemporaryDatabases::forget($this->database);
                (new \Illuminate\Filesystem\Filesystem())->deleteDirectory(sys_get_temp_dir().'/'.$this->database);
            }
        } finally {
            try {
                foreach ($this->previousFrameworkState as [$property,$value]) $property->setValue(null,$value);
                if ($this->capsule) {
                    foreach ($this->capsule->getDatabaseManager()->getConnections() as $connection) $connection->disconnect();
                }
                if ($this->previousContainer) {
                \Illuminate\Database\Eloquent\Model::clearBootedModels();
                if ($this->previousDispatcher) \Illuminate\Database\Eloquent\Model::setEventDispatcher($this->previousDispatcher);
                else \Illuminate\Database\Eloquent\Model::unsetEventDispatcher();
                if ($this->previousResolver) {
                    \Illuminate\Database\Eloquent\Model::setConnectionResolver($this->previousResolver);
                } else {
                    \Illuminate\Database\Eloquent\Model::unsetConnectionResolver();
                }
                Facade::clearResolvedInstances();
                Facade::setFacadeApplication($this->previousFacade);
                Container::setInstance($this->previousContainer);
                }
                parent::tearDown();
            } finally {
                // PHPUnit retains completed test objects; release their owned application graph.
                $this->capsule=null;
                $this->previousContainer=null;
                $this->previousFacade=null;
                $this->previousResolver=null;
                $this->previousDispatcher=null;
                $this->previousFrameworkState=[];
                unset($tenant,$connection);
                gc_collect_cycles();
            }
        }
    }

    private function migrations(string ...$patterns): array
    {
        \App\Database\PostgresBootstrap::install($this->capsule->getConnection());
        $paths = [];
        foreach ($patterns as $pattern) $paths = array_merge($paths, glob(database_path($pattern)));
        $paths = array_values(array_unique($paths));
        usort($paths, static fn ($left,$right) => strcmp(basename($left),basename($right)));
        return array_map(function ($path) {
            if (preg_match('/class\s+(\w+)\s+extends\s+\w+/',file_get_contents($path),$class)) {
                if (!class_exists($class[1],false)) require $path;
                return new $class[1]();
            }
            return require $path;
        }, $paths);
    }

    private function prepareFiscalFixtureSchema(): void
    {
        $start = microtime(true);
        $db = $this->capsule->getConnection('tenant');
        if (getenv('SAB_FISCAL_SNAPSHOT') !== '0' && \Tests\Support\FiscalFixtureSnapshot::restore($db)) {
            if (getenv('SAB_FISCAL_TIMING') === '1') fwrite(STDERR, sprintf("fixture restaurado: %.3f s\n", microtime(true) - $start));
            return;
        }
        foreach ($this->migrations('migrations/tenant/*.php') as $migration) $migration->up();
        $migrated = microtime(true);
        (new \Database\Seeders\TenancyDatabaseSeeder())->setContainer(Container::getInstance())->run();
        $seeded = microtime(true);
        if (getenv('SAB_FISCAL_SNAPSHOT') !== '0') \Tests\Support\FiscalFixtureSnapshot::capture($db);
        if (getenv('SAB_FISCAL_TIMING') === '1') fwrite(STDERR, sprintf("fixture migraciones %.3f s; semillas %.3f s; captura %.3f s\n", $migrated - $start, $seeded - $migrated, microtime(true) - $seeded));
    }

    public function test_fiscal_fixture_snapshot_preserves_schema_seed_data_and_database_isolation(): void
    {
        if (getenv('SAB_FISCAL_SNAPSHOT') === '0') $this->markTestSkipped('La restauración del fixture está deshabilitada.');
        $this->prepareFiscalFixtureSchema();
        $source = $this->capsule->getConnection('tenant');
        $other = 'sab_fiscal_test_' . bin2hex(random_bytes(6));
        $control = $this->capsule->getConnection('control');
        $control->statement('CREATE DATABASE "' . $other . '" TEMPLATE template0');
        \Tests\Support\FiscalTemporaryDatabases::register($other, $control->getPdo());
        try {
            $config = $source->getConfig();
            $config['database'] = $other;
            $this->capsule->addConnection($config, 'fiscal_snapshot_check');
            $target = $this->capsule->getConnection('fiscal_snapshot_check');
            self::assertTrue(\Tests\Support\FiscalFixtureSnapshot::restore($target, true));
            $targetSchema=$this->schemaSnapshot($target);
            foreach ($this->schemaSnapshot($source) as $table => $definition) {
                self::assertSame(hash('sha256',json_encode($definition)), hash('sha256',json_encode($targetSchema[$table])), 'Esquema restaurado: '.$table);
            }
            $sourceRows=$this->seedSnapshot($source,false);$targetRows=$this->seedSnapshot($target,false);
            foreach ($sourceRows as $table=>$rows) self::assertSame(hash('sha256',json_encode($rows)),hash('sha256',json_encode($targetRows[$table])), 'Datos restaurados: '.$table);
            $target->table('payment_method_types')->where('id', '01')->update(['description' => 'Sólo en la copia']);
            self::assertNotSame('Sólo en la copia', $source->table('payment_method_types')->where('id', '01')->value('description'));
            try {
                $target->table('payment_method_types')->where('id', '01')->update(['currency_type_id' => 'invalid']);
                self::fail('La copia debe conservar sus claves foráneas.');
            } catch (\Illuminate\Database\QueryException $error) {
                self::assertSame('23503', $error->getCode());
            }
        } finally {
            $this->capsule->getDatabaseManager()->purge('fiscal_snapshot_check');
            $control->statement('DROP DATABASE "' . $other . '" WITH (FORCE)');
            \Tests\Support\FiscalTemporaryDatabases::forget($other);
        }
    }

    public function test_postgresql_schema_preserves_all_columns_nullability_defaults_and_identity_contracts(): void
    {
        $this->prepareFiscalFixtureSchema();$db=$this->capsule->getConnection('tenant');
        $directory=base_path('informes/evidencias/postgresql_17_implementacion');
        $contract=json_decode(file_get_contents($directory.'/esquema.json'),true,512,JSON_THROW_ON_ERROR);
        self::assertSame('pgsql',$contract['provenance']['engine_under_test']);
        // The archived contract covers 4,380 columns. These additions were
        // explicitly reviewed after the archive was created.
        $additions=[
            'configurations'=>['sales_documents_ves'=>['boolean',false,false,'true']],
            'payment_method_types'=>['available_for_change'=>['boolean',false,false,'false']],
            'document_change_disbursements'=>[
                'id'=>['bigint',false,true], 'document_id'=>['bigint',false,false],
                'document_payment_id'=>['bigint',false,false], 'position'=>['integer',false,false],
                'payment_method_type_id'=>['char(2)',false,false], 'currency_type_id'=>['varchar(255)',false,false],
                'original_amount'=>['numeric(30,12)',false,false], 'exchange_rate'=>['numeric(18,8)',false,false],
                'date_of_payment'=>['date',false,false], 'reference'=>['varchar(255)',true,false],
                'calculation_snapshot'=>['json',false,false], 'context'=>['json',false,false],
                'reversed_at'=>['timestamp',true,false], 'created_at'=>['timestamp',true,false], 'updated_at'=>['timestamp',true,false],
            ],
            'sellnow_sales'=>[
                'id'=>['bigint',false,true], 'client_sale_id'=>['uuid',false,false], 'device_id'=>['uuid',false,false],
                'user_id'=>['bigint',false,false], 'establishment_id'=>['bigint',false,false],
                'kind'=>['varchar(20)',false,false], 'payload_hash'=>['char(64)',false,false],
                'original_cash_id'=>['bigint',false,false], 'cash_id'=>['bigint',false,false],
                'recorded_at'=>['timestamp',false,false], 'status'=>['varchar(24)',false,false,'pending'],
                'document_id'=>['bigint',true,false], 'sale_note_id'=>['bigint',true,false],
                'envelope'=>['text',false,false], 'result'=>['text',true,false], 'error'=>['text',true,false],
                'effects'=>['text',true,false], 'cash_history'=>['text',true,false],
                'created_at'=>['timestamp',true,false], 'updated_at'=>['timestamp',true,false],
            ],
        ];
        $normalize=static fn(string $type): string=>str_replace(
            ['timestamp(0) without time zone','timestamp without time zone','time(0) without time zone','time without time zone'],
            ['timestamp','timestamp','time','time'],$type);
        $expectedTables=array_merge(array_column($contract['tables'],'table'),['document_change_disbursements','sellnow_sales']);sort($expectedTables);
        self::assertSame($expectedTables,\App\Database\SchemaInspector::tables($db));$columns=0;$defaults=[];
        foreach ($contract['tables'] as $table) {
            $actual=[];foreach (\App\Database\SchemaInspector::columns($db,$table['table']) as $column) $actual[$column->name]=$column;
            self::assertEqualsCanonicalizing(array_merge(array_keys($table['type_map']),array_keys($additions[$table['table']] ?? [])),array_keys($actual),$table['table']);
            foreach ($table['type_map'] as $field=>$type) {
                $column=$actual[$field];$before=$table['column_contract'][$field];$label=$table['table'].'.'.$field;$columns++;
                $normalized=$normalize($column->type);
                self::assertSame($type,$normalized,$label.' tipo PostgreSQL');
                self::assertSame($before['nullable'],(bool)$column->nullable,$label.' nulabilidad');
                self::assertSame($before['sequence'],(bool)$column->has_sequence,$label.' secuencia');
                if ($column->generated || $column->has_sequence) continue;
                if ($before['default']===null) {self::assertNull($column->default,$label.' sin valor predeterminado');continue;}
                self::assertNotNull($column->default,$label.' valor predeterminado');
                if (strtoupper($before['default'])==='CURRENT_TIMESTAMP') {
                    self::assertMatchesRegularExpression('/statement_timestamp\(\)/i',$column->default,$label);continue;
                }
                $defaults[]=[$label,$type,$before['default'],$column->default];
            }
        }
        // Catalog expressions come from our own disposable schema, never from request input.
        foreach (array_chunk($defaults,100) as $chunk) {
            $select=[];foreach ($chunk as $index=>$entry) $select[]='('.$entry[3].')::text AS d'.$index;
            $values=(array)$db->selectOne('SELECT '.implode(',',$select));
            foreach ($chunk as $index=>[$label,$type,$before]) {
                $after=$values['d'.$index];
                if ($type==='boolean') {
                    self::assertContains((string)$before,['0','1','false','true'],$label.' debe ser un indicador booleano en la referencia');
                    self::assertSame(in_array((string)$before,['1','true'],true),$after==='true',$label.' default');
                }
                elseif (preg_match('/^(?:numeric|smallint|integer|bigint)/',$type)) self::assertTrue(\Brick\Math\BigRational::of($before)->isEqualTo(\Brick\Math\BigRational::of($after)),$label.' default exacto');
                else self::assertSame((string)$before,(string)$after,$label.' default');
            }
        }
        self::assertSame(4380,$columns);self::assertSame($contract['column_count'],$columns);
        $extraColumns=0;
        foreach ($additions as $table=>$fields) {
            $actual=array_column(\App\Database\SchemaInspector::columns($db,$table),null,'name');
            if ($table!=='payment_method_types') self::assertEqualsCanonicalizing(array_keys($fields),array_keys($actual),$table);
            foreach ($fields as $field=>$definition) {
                [$type,$nullable,$sequence]=$definition;$column=$actual[$field];$label=$table.'.'.$field;$extraColumns++;
                self::assertSame($type,$normalize($column->type),$label);
                self::assertSame($nullable,(bool)$column->nullable,$label.' nulabilidad');
                self::assertSame($sequence,(bool)$column->has_sequence,$label.' secuencia');
                if ($sequence) continue;
                if (!array_key_exists(3,$definition)) self::assertNull($column->default,$label.' sin default');
                else {
                    self::assertNotNull($column->default,$label.' default');
                    self::assertSame($definition[3],$db->selectOne('SELECT ('.$column->default.')::text AS value')->value,$label.' default');
                }
            }
        }
        self::assertSame(36,$extraColumns);self::assertSame(4416,$columns+$extraColumns);
        $keys=\App\Database\SchemaInspector::foreignKeys($db);self::assertCount(558,$keys);
        $extraKeys=[
            'document_change_document_fk'=>['document_id','documents'],
            'document_change_payment_fk'=>['document_payment_id','document_payments'],
            'document_change_method_fk'=>['payment_method_type_id','payment_method_types'],
            'document_change_currency_fk'=>['currency_type_id','cat_currency_types'],
        ];
        self::assertCount(554,array_filter($keys,static fn($key)=>!isset($extraKeys[$key->name])));
        foreach ($extraKeys as $name=>[$column,$target]) {
            $extra=array_values(array_filter($keys,static fn($key)=>$key->name===$name));self::assertCount(1,$extra,$name);
            $key=$extra[0];self::assertSame('document_change_disbursements',$key->table);
            self::assertSame($column,$key->column);self::assertSame($target,$key->reference_table);
            self::assertSame('id',$key->reference_column);self::assertSame('r',$key->delete_action);self::assertSame('a',$key->update_action);
        }
        foreach ($keys as $key) {self::assertSame(1,(int)$key->deferrable,$key->name);self::assertSame(0,(int)$key->initially_deferred,$key->name);}
        self::assertSame(0,(int)\App\Database\SchemaInspector::indexes($db,'sellnow_sales','sellnow_sales_client_sale_id_unique')[0]->non_unique);
    }

    public function test_fiscal_temporary_database_is_removed_after_process_error(): void
    {
        $name = 'sab_fiscal_test_' . bin2hex(random_bytes(6));
        $connection = $this->capsule->getConnection('control')->getConfig();
        $code = <<<'PHP'
require 'vendor/autoload.php';
$input = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
$c = $input['connection'];
$pdo = new PDO('pgsql:host='.$c['host'].';port='.$c['port'].';dbname=postgres', $c['username'], $c['password']);
$pdo->exec('CREATE DATABASE "'.$input['name'].'"');
\Tests\Support\FiscalTemporaryDatabases::register($input['name'], $pdo);
exit(9);
PHP;
        $process = proc_open([PHP_BINARY, '-r', $code], [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes, base_path());
        self::assertIsResource($process);
        try {
            fwrite($pipes[0], json_encode(['name' => $name, 'connection' => $connection], JSON_THROW_ON_ERROR));
            fclose($pipes[0]);
            $stdout = stream_get_contents($pipes[1]); fclose($pipes[1]);
            $stderr = stream_get_contents($pipes[2]); fclose($pipes[2]);
            self::assertSame(9, proc_close($process), $stdout . $stderr);
            $exists = $this->capsule->getConnection('control')->selectOne('SELECT datname FROM pg_database WHERE datname = ?', [$name]);
            self::assertNull($exists, 'La base del proceso fallido debe limpiarse al terminar.');
        } finally {
            $this->capsule->getConnection('control')->statement('DROP DATABASE IF EXISTS "' . $name . '"');
        }
    }

    // ######## INICIO CALCULOS EXACTOS SAB ########
    public function test_central_exact_schema_range_snapshot_rollback_and_repeat_in_postgresql(): void
    {
        $migrations=[];
        foreach (['2019_01_28_092812_create_plans_table.php','2022_12_23_164914_add_sales_limit_to_plans.php',
            '2025_06_10_092834_create_massive_invoices_table.php'] as $file) $migrations=array_merge($migrations,$this->migrations('migrations/'.$file));
        $db=$this->capsule->getConnection('system');
        for ($cycle=0;$cycle<2;$cycle++) {
            foreach ($migrations as $migration) $migration->up();
            self::assertSame('numeric(32,12)',\App\Database\SchemaInspector::columns($db, "plans", 'sales_limit')[0]->type);
            $plan=\App\Models\System\Plan::create(['name'=>'Exacto','pricing'=>\App\Support\Exact\Decimal::of(1)->dividedBy(3),
                'sales_limit'=>'99999999999999999999.999999999999','limit_users'=>1,'limit_documents'=>1,'plan_documents'=>[]]);
            $plan=$plan->fresh();
            self::assertSame('99999999999999999999.999999999999',$plan->sales_limit);
            self::assertSame('1',\App\Support\Exact\Decimal::represent($plan->exactValue('pricing')->multipliedBy(3)));
            $db->beginTransaction();
            $plan->pricing=\App\Support\Exact\Decimal::of(2)->dividedBy(7);$plan->save();
            $db->rollBack();
            self::assertSame(['n'=>'1','d'=>'3'],\App\Support\Exact\Decimal::fraction($plan->fresh()->exactValue('pricing')));
            try {
                $plan->pricing=\App\Support\Exact\Decimal::of('1000000000000000000');$plan->save();
                self::fail('El valor fuera de rango no puede llegar a PostgreSQL.');
            } catch (\Illuminate\Validation\ValidationException $error) {self::assertArrayHasKey('pricing',$error->errors());}
            self::assertSame(['n'=>'1','d'=>'3'],\App\Support\Exact\Decimal::fraction($plan->fresh()->exactValue('pricing')));
            foreach (array_reverse($migrations) as $migration) $migration->down();
            self::assertFalse($db->getSchemaBuilder()->hasTable('plans'));
            self::assertFalse($db->getSchemaBuilder()->hasTable('massive_invoices'));
        }
    }
    // ######## FIN CALCULOS EXACTOS SAB ########

    public function test_change_disbursement_schema_is_created_and_reversible(): void
    {
        $db = $this->capsule->getConnection('tenant');
        $migrations = $this->migrations('migrations/tenant/*.php');
        for ($cycle = 0; $cycle < 2; $cycle++) {
            foreach ($migrations as $migration) $migration->up();
            self::assertTrue($db->getSchemaBuilder()->hasColumn('payment_method_types', 'available_for_change'));
            self::assertTrue($db->getSchemaBuilder()->hasTable('document_change_disbursements'));
            foreach (['document_change_document_fk', 'document_change_payment_fk', 'document_change_method_fk', 'document_change_currency_fk'] as $constraint) {
                self::assertCount(1, array_filter(\App\Database\SchemaInspector::foreignKeys($db, 'document_change_disbursements'), static fn($key) => $key->name === $constraint));
            }
            self::assertSame(0, (int) \App\Database\SchemaInspector::indexes($db, 'document_change_disbursements', 'document_change_payment_position_unique')[0]->non_unique);
            foreach (array_reverse($migrations) as $migration) $migration->down();
            self::assertFalse($db->getSchemaBuilder()->hasTable('document_change_disbursements'));
        }
    }

    // ######## INICIO MONEDA DOCUMENTAL DE VENTAS ########
    public function test_new_sales_documents_validate_currency_and_reprice_frozen_usd(): void
    {
        [$db, , $input] = $this->bootHkaFixture(false);
        config(['app.key' => 'test-quote-confirmation-key']);
        self::assertTrue(\App\Services\SalesDocumentCurrency::usesBolivars());
        try {
            (new \App\CoreFacturalo\Facturalo())->save(array_replace($input, ['currency_type_id' => 'USD']));
            self::fail('Una factura USD debe rechazarse antes de guardar.');
        } catch (\Illuminate\Validation\ValidationException $error) {
            self::assertArrayHasKey('currency_type_id', $error->errors());
        }
        self::assertSame(0, $db->table('documents')->count());
        $quotationId = $db->table('quotations')->insertGetId([
            'user_id' => $input['user_id'], 'external_id' => (string) \Illuminate\Support\Str::uuid(),
            'establishment_id' => $input['establishment_id'], 'establishment' => json_encode($input['establishment']),
            'fiscal_environment' => 'demo', 'state_type_id' => '05', 'prefix' => 'COT',
            'date_of_issue' => '2026-09-01', 'time_of_issue' => '09:00:00',
            'customer_id' => $input['customer_id'], 'customer' => json_encode($input['customer']),
            'currency_type_id' => 'VES', 'exchange_rate_sale' => '40', 'total' => '400',
        ]);
        $quotation = \App\Models\Tenant\Quotation::findOrFail($quotationId);
        $item = array_replace($input['items'][0], [
            'quantity' => '1', 'unit_price' => '400', 'affectation_igv_type_id' => '20',
            'price_input' => ['amount' => '10', 'currency_type_id' => 'USD', 'includes_iva' => true],
        ]);
        $quotation->items()->create($item);
        $db->table('exchange_rates')->insert([
            'date' => $input['date_of_issue'], 'date_original' => $input['date_of_issue'],
            'sale' => '45', 'purchase' => '45', 'sale_original' => '45', 'purchase_original' => '45',
        ]);
        unset($item['price_input']);
        $input['items'] = [$item];
        $input['payments'] = [];
        $input['payment_condition_id'] = '02';
        $input['quotation_id'] = $quotationId;
        $preview = \App\Services\Fiscal\InvoiceSourcePrices::preview($input);
        self::assertSame('400.00', $preview['source_total']);
        self::assertSame('450', $preview['total']);
        $manual = $input;
        $manual['items'][0]['price_input'] = ['amount' => '410', 'currency_type_id' => 'VES', 'includes_iva' => true];
        self::assertNull(\App\Services\Fiscal\InvoiceSourcePrices::preview($manual));

        try {
            (new \App\CoreFacturalo\Facturalo())->save($input);
            self::fail('La factura requiere confirmar el recálculo.');
        } catch (\Illuminate\Validation\ValidationException $error) {
            self::assertArrayHasKey('source_price_confirmation', $error->errors());
        }
        self::assertSame(0, $db->table('documents')->count());
        $input['source_price_confirmation'] = $preview['confirmation'];
        $invoice = (new \App\CoreFacturalo\Facturalo())->save($input)->getDocument();
        self::assertSame('VES', $invoice->currency_type_id);
        self::assertSame('450', \App\Support\Exact\Decimal::represent($invoice->exactValue('total')));
        self::assertSame('400', \App\Support\Exact\Decimal::represent($quotation->fresh()->exactValue('total')));

        $db->statement('ALTER TABLE documents DROP CONSTRAINT documents_currency_ves_check');
        $db->statement('ALTER TABLE configurations DROP COLUMN sales_documents_ves');
        self::assertFalse(\App\Services\SalesDocumentCurrency::usesBolivars());
        \App\Services\SalesDocumentCurrency::validate('USD');
        unset($input['quotation_id'], $input['source_price_confirmation']);
        $input['external_id'] = (string) \Illuminate\Support\Str::uuid();
        $input['currency_type_id'] = 'USD';
        $legacyInvoice = (new \App\CoreFacturalo\Facturalo())->save($input)->getDocument();
        self::assertSame('USD', $legacyInvoice->fresh()->currency_type_id);
    }
    // ######## FIN MONEDA DOCUMENTAL DE VENTAS ########

    public function test_fresh_tenant_migrations_seed_rollback_and_repeat(): void
    {
        $db = $this->capsule->getConnection('tenant');
        $migrations = $this->migrations('migrations/tenant/*.php');
        $snapshot = null;
        $seedSnapshot = null;
        for ($cycle = 0; $cycle < 2; $cycle++) {
            foreach ($migrations as $migration) {
                $migration->up();
            }
            (new \Database\Seeders\TenancyDatabaseSeeder())->setContainer(Container::getInstance())->run();
            $this->assertFiscalSchema($db);
            self::assertTrue($db->getSchemaBuilder()->hasColumn('hotel_rents', 'calculation_snapshot'));
            self::assertSame('numeric(30,12)', \App\Database\SchemaInspector::column($db, "hotel_rents", 'arrears')->type);
            foreach (['fiscal_sequences', 'fiscal_profiles', 'fiscal_control_lots', 'fiscal_number_reservations', 'fiscal_emission_attempts', 'fiscal_numbering_audits', 'fiscal_demo_receipts', 'fiscal_hka_assignments'] as $retired) {
                self::assertFalse($db->getSchemaBuilder()->hasTable($retired), $retired);
            }
            foreach (['documents', 'dispatches'] as $table) {
                self::assertTrue($db->getSchemaBuilder()->hasColumn($table, 'control_number'));
            }
            // ########## INICIO CAMBIO CATÁLOGOS DE NOMBRES
            $methods = $db->table('payment_method_types')->orderBy('id')->get(['id', 'hka_code', 'is_active']);
            self::assertCount(10, $methods);
            self::assertSame(7, $methods->pluck('hka_code')->unique()->count());
            self::assertSame(4, $methods->where('hka_code', '99')->count());
            self::assertFalse($methods->contains('id', '06'));
            self::assertSame(4, $methods->where('is_active', 1)->count());
            self::assertFalse($db->getSchemaBuilder()->hasColumn('payment_method_types', 'charge'));
            self::assertFalse($db->getSchemaBuilder()->hasTable('card_brands'));
            // ######## INICIO MONEDA DEL MEDIO DE COBRO ########
            foreach (['sale_note_payments', 'income_payments', 'quotation_payments', 'contract_payments', 'technical_service_payments', 'hotel_rent_item_payments'] as $table) {
                foreach (['currency_type_id', 'original_amount', 'exchange_rate', 'exchange_rate_source', 'exchange_rate_date', 'payment_method_snapshot'] as $column) {
                    self::assertTrue($db->getSchemaBuilder()->hasColumn($table, $column), $table.'.'.$column);
                }
                self::assertCount(1, $db->select('SELECT con.conname FROM pg_constraint con JOIN pg_class tbl ON tbl.oid=con.conrelid JOIN pg_namespace ns ON ns.oid=tbl.relnamespace JOIN pg_class rt ON rt.oid=con.confrelid CROSS JOIN LATERAL generate_subscripts(con.conkey,1) k(i) JOIN pg_attribute a ON a.attrelid=tbl.oid AND a.attnum=con.conkey[k.i] JOIN pg_attribute ra ON ra.attrelid=rt.oid AND ra.attnum=con.confkey[k.i] WHERE ns.nspname=current_schema() AND current_database() = ? AND tbl.relname = ? AND con.conname = ?', [$this->database, $table, $table.'_currency_fk']));
            }
            self::assertTrue($db->getSchemaBuilder()->hasColumn('hotel_rent_item_payments', 'source_sale_note_payment_id'));
            self::assertTrue($db->getSchemaBuilder()->hasColumn('transfer_account_payments', 'currency_type_id'));
            self::assertCount(1, $db->select("SELECT con.conname FROM pg_constraint con JOIN pg_class tbl ON tbl.oid=con.conrelid JOIN pg_namespace ns ON ns.oid=tbl.relnamespace JOIN pg_class rt ON rt.oid=con.confrelid CROSS JOIN LATERAL generate_subscripts(con.conkey,1) k(i) JOIN pg_attribute a ON a.attrelid=tbl.oid AND a.attnum=con.conkey[k.i] JOIN pg_attribute ra ON ra.attrelid=rt.oid AND ra.attnum=con.confkey[k.i] WHERE ns.nspname=current_schema() AND current_database() = ? AND tbl.relname = 'transfer_account_payments' AND con.conname = 'transfer_account_currency_fk'", [$this->database]));
            self::assertCount(1, $db->select("SELECT con.conname FROM pg_constraint con JOIN pg_class tbl ON tbl.oid=con.conrelid JOIN pg_namespace ns ON ns.oid=tbl.relnamespace JOIN pg_class rt ON rt.oid=con.confrelid CROSS JOIN LATERAL generate_subscripts(con.conkey,1) k(i) JOIN pg_attribute a ON a.attrelid=tbl.oid AND a.attnum=con.conkey[k.i] JOIN pg_attribute ra ON ra.attrelid=rt.oid AND ra.attnum=con.confkey[k.i] WHERE ns.nspname=current_schema() AND current_database() = ? AND tbl.relname = 'hotel_rent_item_payments' AND con.conname = 'hotel_receipt_source_fk'", [$this->database]));
            self::assertSame(0, (int) \App\Database\SchemaInspector::indexes($db, "hotel_rent_item_payments", 'hotel_receipt_source_unique')[0]->non_unique);
            self::assertTrue($db->getSchemaBuilder()->hasColumn('received_payments', 'currency_type_id'));
            self::assertFalse((bool) \App\Database\SchemaInspector::columns($db, "received_payments", 'currency_type_id')[0]->nullable);
            self::assertCount(1, $db->select("SELECT con.conname FROM pg_constraint con JOIN pg_class tbl ON tbl.oid=con.conrelid JOIN pg_namespace ns ON ns.oid=tbl.relnamespace JOIN pg_class rt ON rt.oid=con.confrelid CROSS JOIN LATERAL generate_subscripts(con.conkey,1) k(i) JOIN pg_attribute a ON a.attrelid=tbl.oid AND a.attnum=con.conkey[k.i] JOIN pg_attribute ra ON ra.attrelid=rt.oid AND ra.attnum=con.confkey[k.i] WHERE ns.nspname=current_schema() AND current_database() = ? AND tbl.relname = 'received_payments' AND con.conname = 'received_payments_currency_fk'", [$this->database]));
            // ######## FIN MONEDA DEL MEDIO DE COBRO ########
            foreach (['payment_method_types', 'expense_method_types', 'document_payments', 'sale_note_payments', 'purchase_payments', 'quotation_payments', 'contract_payments', 'income_payments', 'expense_payments', 'bank_loan_payments'] as $table) {
                self::assertFalse($db->getSchemaBuilder()->hasColumn($table, 'has_card'), $table);
                self::assertFalse($db->getSchemaBuilder()->hasColumn($table, 'card_brand_id'), $table);
            }
            self::assertTrue($db->getSchemaBuilder()->hasColumn('account_payments', 'card_brand_id'));
            self::assertSame(2, $db->table('payment_method_types')->where('currency_type_id', 'USD')->count());
            self::assertSame(['10', '13'], $db->table('payment_method_types')->where('currency_type_id', 'USD')->orderBy('id')->pluck('id')->all());
            self::assertSame(0, $db->table('payment_method_types')->whereNotNull('iva_rate_type_id')->count());
            self::assertCount(2, $db->select("SELECT con.conname FROM pg_constraint con JOIN pg_class tbl ON tbl.oid=con.conrelid JOIN pg_namespace ns ON ns.oid=tbl.relnamespace JOIN pg_class rt ON rt.oid=con.confrelid CROSS JOIN LATERAL generate_subscripts(con.conkey,1) k(i) JOIN pg_attribute a ON a.attrelid=tbl.oid AND a.attnum=con.conkey[k.i] JOIN pg_attribute ra ON ra.attrelid=rt.oid AND ra.attnum=con.confkey[k.i] WHERE ns.nspname=current_schema() AND current_database() = ? AND tbl.relname = 'payment_method_types' AND con.conname IN ('payment_method_currency_fk', 'payment_method_tax_fk')", [$this->database]));
            self::assertSame(0, $db->table('payment_method_types')->where('collection_kind', 'unclassified')->count());
            self::assertSame(4, \App\Models\Tenant\PaymentMethodType::getPaymentMethodTypes()->count());
            $paymentRules = (new \App\Http\Requests\Tenant\DocumentPaymentRequest())->rules();
            unset($paymentRules['document_id']); // This assertion isolates payment-method eligibility.
            $validator = Container::getInstance()->make('validator');
            $paymentInput = [
                'date_of_payment' => '2026-09-13', 'payment_method_type_id' => '14',
                'payment_destination_id' => 'cash', 'payment' => 10,
            ];
            self::assertTrue($validator->make(array_replace($paymentInput, ['payment_method_type_id' => '01']), $paymentRules)->passes());
            self::assertTrue($validator->make($paymentInput, $paymentRules)->fails());
            \App\Models\Tenant\PaymentMethodType::assertActiveForPayment('01');
            try {
                \App\Models\Tenant\PaymentMethodType::assertActiveForPayment('14');
                self::fail('Un método inexistente no puede registrarse como pago.');
            } catch (\Illuminate\Validation\ValidationException $exception) {
                self::assertArrayHasKey('payment_method_type_id', $exception->errors());
            }
            $column = \App\Database\SchemaInspector::columns($db, "payment_method_types", 'hka_code');
            self::assertSame('char(2)', $column[0]->type);
            self::assertTrue((bool) $column[0]->nullable);
            $hkaIndex = \App\Database\SchemaInspector::indexes($db, "payment_method_types", 'payment_method_types_hka_code_index');
            self::assertCount(1, $hkaIndex);
            self::assertSame(1, (int) $hkaIndex[0]->non_unique);
            $db->table('companies')->insert(['id'=>999, 'identity_document_type_id'=>'6', 'number'=>'J123456789', 'name'=>'Temporal', 'fiscal_environment'=>'demo', 'fiscal_emission_mode'=>'free_form']);
            $customMethod = \Modules\Sale\Http\Requests\PaymentMethodTypeRequest::create('/', 'POST', [
                'id' => '27', 'description' => 'Método local de prueba', 'is_active' => 1, 'collection_kind'=>'cash', 'currency_type_id'=>'VES',
            ]);
            app(\Modules\Sale\Http\Controllers\PaymentMethodTypeController::class)->store($customMethod);
            self::assertNull($db->table('payment_method_types')->where('id', '27')->value('hka_code'));
            $db->table('payment_method_types')->where('id', '27')->delete();
            $db->table('companies')->where('id',999)->delete();
            // ######### FIN CAMBIO CATÁLOGOS DE NOMBRES
            // ########## INICIO CAMBIO CATÁLOGOS DE NOMBRES
            // ########## INICIO CAMBIO AFECTACIÓN IVA
            self::assertSame([
                ['IVA', 'Impuesto al valor agregado - IVA'],
                ['ISLR', 'Impuesto sobre la renta - ISLR'],
                ['MUNICIPAL', 'Impuesto Municipal o Estadal'],
                ['IGTF', 'Impuesto a las grandes transacciones'],
                ['LISAEA', 'Impuesto sobre Alcohol y Especies Alcohólicas - LISAEA'],
            ], $db->table('cat_taxes_types')->orderByRaw("CASE id WHEN 'IVA' THEN 1 WHEN 'ISLR' THEN 2 WHEN 'MUNICIPAL' THEN 3 WHEN 'IGTF' THEN 4 WHEN 'LISAEA' THEN 5 ELSE 0 END")
                ->get(['id', 'description'])->map(static fn ($row): array => [$row->id, $row->description])->all());
            self::assertSame([
                ['A', 'Alícuota Adicional (suntuario)', '31.00', 'IVA'],
                ['E', 'Exento, Exonerado o No Gravado', '0.00', 'IVA'],
                ['G', 'Alícuota General', '16.00', 'IVA'],
                ['IGTF', 'Impuesto a las Grandes Transacciones Financieras', '3.00', 'IGTF'],
                ['P', 'Percibido', '0.00', 'IVA'],
                ['R', 'Alícuota Reducida', '8.00', 'IVA'],
            ], $db->table('cat_iva_rate_types')->orderBy('id')->get(['id', 'description', 'percentage', 'tax_kind'])
                ->map(static fn ($row): array => [$row->id, $row->description, number_format((float) $row->percentage, 2, '.', ''), $row->tax_kind])->all());
            self::assertCount(1, \App\Database\SchemaInspector::indexes($db, "cat_iva_rate_types", 'PRIMARY'));
            $taxKey = $db->selectOne('SELECT rt.relname AS ref_table, ra.attname AS ref_column FROM pg_constraint con JOIN pg_class tbl ON tbl.oid=con.conrelid JOIN pg_namespace ns ON ns.oid=tbl.relnamespace JOIN pg_class rt ON rt.oid=con.confrelid JOIN pg_attribute ra ON ra.attrelid=rt.oid AND ra.attnum=con.confkey[1] WHERE ns.nspname=current_schema() AND current_database() = ? AND tbl.relname = ? AND con.conname = ?', [$this->database, 'cat_iva_rate_types', 'cat_iva_rate_types_tax_kind_foreign']);
            self::assertSame('cat_taxes_types', $taxKey->ref_table);
            self::assertSame('id', $taxKey->ref_column);
            foreach ([
                fn () => $db->table('cat_iva_rate_types')->where('id', 'G')->update(['tax_kind' => 'NO_EXISTE']),
                fn () => $db->table('cat_taxes_types')->where('id', 'IVA')->delete(),
                fn () => $db->table('cat_taxes_types')->where('id', 'IVA')->update(['id' => 'IVA_NUEVO']),
            ] as $operation) {
                try {
                    $operation();
                    self::fail('La clave foránea debe rechazar la operación.');
                } catch (\Illuminate\Database\QueryException $error) {
                    self::assertSame('23503', $error->getCode());
                }
            }
            self::assertSame('IVA', $db->table('cat_iva_rate_types')->where('id', 'G')->value('tax_kind'));
            // ######### FIN CAMBIO AFECTACIÓN IVA
            self::assertTrue($db->getSchemaBuilder()->hasColumn('cat_identity_document_types', 'external_document_examples'));
            self::assertSame(9, $db->table('cat_identity_document_types')->count());
            self::assertSame(9, $db->table('cat_identity_document_types')->where('active', 1)->count());
            self::assertSame(1, $db->table('cat_identity_document_types')->whereNotNull('external_document_examples')->count());
            $nondomiciled = $db->table('cat_identity_document_types')->where('id', 'ND')->first(['active', 'description', 'external_document_examples']);
            self::assertSame(1, (int) $nondomiciled->active);
            self::assertSame('No Domiciliado', $nondomiciled->description);
            self::assertSame('RUT, NIT', $nondomiciled->external_document_examples);
            self::assertTrue($db->getSchemaBuilder()->hasColumn('cat_operation_types', 'incoterm'));
            self::assertSame([
                ['0101', 'Venta interna', 1, 0, null],
                ['0200', 'Exportación de Bienes', 0, 1, null],
                ['0201', 'Exportación FOB', 1, 1, 'FOB'],
                ['0202', 'Exportación CIF', 1, 1, 'CIF'],
                ['0203', 'Exportación EXW', 1, 1, 'EXW'],
            ], $db->table('cat_operation_types')->orderBy('id')->get(['id', 'description', 'active', 'exportation', 'incoterm'])
                ->map(static fn ($row): array => [$row->id, $row->description, (int) $row->active, (int) $row->exportation, $row->incoterm])->all());
            self::assertSame(['0101', '0201', '0202', '0203'], $db->table('cat_operation_types')->where('active', 1)->pluck('id')->all());
            $concepts = $db->table('cat_retention_concept')->orderBy('id')->get(['id', 'percentage_label']);
            self::assertCount(86, $concepts);
            self::assertSame('001', $concepts[0]->id);
            self::assertSame('Variable', $concepts[0]->percentage_label);
            self::assertSame('086', $concepts[85]->id);
            self::assertStringContainsString('22 %', $concepts[4]->percentage_label);
            self::assertSame([
                ['01', 'Dividendo en acciones', 'DA'],
                ['02', 'Dividendo en efectivo', 'DE'],
                ['03', 'Venta de acciones', 'VA'],
            ], $db->table('cat_retention_types')->orderBy('id')->get(['id', 'description', 'abbreviation'])
                ->map(static fn ($row): array => [$row->id, $row->description, $row->abbreviation])->all());
            self::assertFalse($db->getSchemaBuilder()->hasColumn('cat_retention_types', 'percentage'));
            self::assertCount(1, \App\Database\SchemaInspector::indexes($db, "cat_retention_concept", 'PRIMARY'));
            self::assertCount(1, \App\Database\SchemaInspector::indexes($db, "cat_retention_types", 'PRIMARY'));
            // ######### FIN CAMBIO CATÁLOGOS DE NOMBRES
            // ########## INICIO CAMBIO CATÁLOGOS DE NOMBRES
            $referenceCatalogs = [
                'cat_product_origins' => [
                    [1, 'Nacional', 1],
                    [2, 'Importado', 1],
                    [3, 'Nacional e Importado', 1],
                ],
                'cat_product_types' => [
                    [1, 'Alcohol', 1],
                    [2, 'Cigarrillos', 1],
                ],
                'cat_taxation_products' => [
                    [1, 'Tierra Firme', 1],
                    [2, 'Régimen Especial', 1],
                ],
            ];
            foreach ($referenceCatalogs as $table => $expectedRows) {
                $rows = $db->table($table)->orderBy('id')->get(['id', 'description', 'active'])
                    ->map(static fn ($row): array => [(int) $row->id, $row->description, (int) $row->active])
                    ->all();
                self::assertSame($expectedRows, $rows, $table);
                $columns = collect(\App\Database\SchemaInspector::columns($db, "$table"))
                    ->map(static fn ($column): array => [
                        $column->name,
                        preg_replace('/^tinyint\(3\) unsigned$/', 'smallint', $column->type),
                        $column->nullable ? 'YES' : 'NO',
                        $column->primary ? 'PRI' : '',
                    ])
                    ->all();
                self::assertSame([
                    ['id', 'smallint', 'NO', 'PRI'],
                    ['description', 'varchar(255)', 'NO', ''],
                    ['active', 'boolean', 'NO', ''],
                ], $columns, $table);
                $primaryKey = \App\Database\SchemaInspector::indexes($db, "$table", 'PRIMARY');
                self::assertCount(1, $primaryKey, $table);
                self::assertSame('id', $primaryKey[0]->column, $table);
            }

            $providerRows = $db->table('cat_providers_types')->orderBy('id')->get(['id', 'code', 'description', 'active'])
                ->map(static fn ($row): array => [(int) $row->id, $row->code, $row->description, (int) $row->active])
                ->all();
            self::assertSame([
                [1, null, 'Normal', 1],
                [2, 'SR', 'Sin RIF', 1],
                [3, 'NR', 'No Residenciado', 1],
                [4, 'ND', 'No Domiciliado', 1],
            ], $providerRows);
            $providerPrimaryKey = \App\Database\SchemaInspector::indexes($db, "cat_providers_types", 'PRIMARY');
            self::assertCount(1, $providerPrimaryKey);
            self::assertSame('id', $providerPrimaryKey[0]->column);
            $providerCodeIndex = \App\Database\SchemaInspector::indexes($db, "cat_providers_types", 'cat_providers_types_code_unique');
            self::assertCount(1, $providerCodeIndex);
            self::assertSame('code', $providerCodeIndex[0]->column);
            self::assertSame(0, (int) $providerCodeIndex[0]->non_unique);

            $regimeRows = $db->table('cat_special_tax_regime')->orderBy('id')->get(['id', 'description', 'active'])
                ->map(static fn ($row): array => [(int) $row->id, $row->description, (int) $row->active])
                ->all();
            self::assertSame([
                [1, 'Zonas económicas especiales', 1],
                [2, 'Zona franca de Paraguaná', 1],
                [3, 'Zona libre de Paraguaná', 1],
                [4, 'Puerto libre Santa Elena de Uairén', 1],
                [5, 'Zona Libre de Mérida', 1],
                [6, 'Puerto libre Estado Nueva Esparta', 1],
                [7, 'Dutty Free', 1],
            ], $regimeRows);
            $regimePrimaryKey = \App\Database\SchemaInspector::indexes($db, "cat_special_tax_regime", 'PRIMARY');
            self::assertCount(1, $regimePrimaryKey);
            self::assertSame('id', $regimePrimaryKey[0]->column);

            $transactionRows = $db->table('cat_transactions_types')->orderBy('id')->get(['id', 'description', 'active'])
                ->map(static fn ($row): array => [(string) $row->id, $row->description, (int) $row->active])
                ->all();
            self::assertSame([
                ['01', 'Registro', 1],
                ['02', 'Complemento', 1],
                ['03', 'Anulación', 1],
                ['04', 'Ajuste', 1],
                ['98', 'ND por IGTF', 1],
                ['99', 'Solo cuando la factura es a Terceros', 1],
            ], $transactionRows);
            $primaryKey = \App\Database\SchemaInspector::indexes($db, "cat_transactions_types", 'PRIMARY');
            self::assertCount(1, $primaryKey);
            self::assertSame('id', $primaryKey[0]->column);

            // ########## INICIO CAMBIO CATÁLOGO MOTIVOS DE TRASLADO VENEZUELA
            self::assertSame([
                ['04', 'Traslado entre almacenes propios', 1, 0],
                ['21', 'Reparación o perfeccionamiento', 1, 0],
                ['22', 'Almacenes, depósitos o bodegas de otros', 1, 0],
                ['23', 'Tránsito aduanero', 1, 0],
                ['24', 'Otras causas (especifique)', 1, 0],
            ], $db->table('cat_transfer_reason_types')->orderBy('id')->get(['id', 'description', 'active', 'discount_stock'])
                ->map(static fn ($row): array => [$row->id, $row->description, (int) $row->active, (int) $row->discount_stock])->all());
            $transferReasonColumn = \App\Database\SchemaInspector::columns($db, "cat_transfer_reason_types", 'id');
            self::assertSame('varchar(2)', $transferReasonColumn[0]->type);
            self::assertTrue((bool)$transferReasonColumn[0]->primary);
            self::assertFalse((bool)$transferReasonColumn[0]->nullable);
            self::assertSame('varchar(2)', \App\Database\SchemaInspector::columns($db, "dispatches", 'transfer_reason_type_id')[0]->type);
            $discountStockColumn = \App\Database\SchemaInspector::columns($db, "dispatches", 'discount_stock')[0];
            self::assertSame('boolean', $discountStockColumn->type);
            self::assertSame('false', $discountStockColumn->default);
            // ########## INICIO RETIRO TRASLADO M1/L1 ##########
            self::assertSame([], \App\Database\SchemaInspector::columns($db, "dispatches", 'is_transport_m1l'));
            self::assertSame([], \App\Database\SchemaInspector::columns($db, "dispatches", 'license_plate_m1l'));
            // ######### FIN RETIRO TRASLADO M1/L1 #########
            // ########## INICIO RETIRO SEMIRREMOLQUE ##########
            self::assertSame([], \App\Database\SchemaInspector::columns($db, "dispatches", 'secondary_license_plates'));
            // ######### FIN RETIRO SEMIRREMOLQUE #########
            self::assertSame('varchar(2)', \App\Database\SchemaInspector::columns($db, "order_forms", 'transfer_reason_type_id')[0]->type);
            // ######### FIN CAMBIO CATÁLOGO MOTIVOS DE TRASLADO VENEZUELA
            // ######### FIN CAMBIO CATÁLOGOS DE NOMBRES
            $current = $this->schemaSnapshot($db);
            foreach (['retentions', 'perceptions', 'purchase_settlements'] as $table) {
                self::assertSame('varchar(20)', \App\Database\SchemaInspector::columns($db, "$table", 'series')[0]->type);
            }
            if (getenv('SAB_EXPORT_CONTRACT') === '1') {
                file_put_contents('/tmp/sab-fresh-final-schema.json', json_encode($current, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            }
            $this->assertForeignKeyIntegrity($db);
            $currentSeeds = $this->seedSnapshot($db);
            if ($snapshot !== null) {
                self::assertSame($snapshot, $current, 'El segundo ciclo debe producir el mismo esquema.');
                self::assertSame($seedSnapshot, $currentSeeds, 'El segundo ciclo debe producir los mismos datos iniciales.');
            }
            $snapshot = $current;
            $seedSnapshot = $currentSeeds;
            $this->assertProductAndVariationPersistence($db);
            $this->assertTransferReasonPersistence($db);
            if ($cycle === 0) $this->assertCommercialDocumentPersistence($db);
            $db->table('companies')->insert([
                'id' => 1, 'identity_document_type_id' => '6', 'number' => 'J123456789',
                'name' => 'Test fiscal', 'trade_name' => 'Test fiscal',
                'fiscal_environment' => 'production', 'fiscal_emission_mode' => 'digital',
            ]);
            self::assertSame(0, (int) $db->table('companies')->value('fiscal_environment_locked'));
            $db->table('fiscal_configuration_audits')->insert([
                'company_id' => 1, 'actor_type' => 'system', 'actor_id' => 1,
                'changed_fields' => '["fiscal_emission_mode"]',
                'fiscal_emission_mode' => 'digital', 'fiscal_environment' => 'production', 'created_at' => '2026-09-10 00:00:00',
            ]);
            self::assertSame(1, $db->table('fiscal_configuration_audits')->count());
            foreach (array_reverse($migrations) as $migration) {
                $migration->down();
            }
            self::assertSame([], \App\Database\SchemaInspector::tables($db), 'Rollback completo sin tablas residuales.');
        }
    }

    private function assertCommercialDocumentPersistence(Connection $db): void
    {
        $previousAuth = app('auth');
        $previousDispatcher = \Illuminate\Database\Eloquent\Model::getEventDispatcher();
        $db->beginTransaction();
        try {
            $app = Container::getInstance();
            (new \Database\Seeders\TenancyDatabaseSeeder())->setContainer($app)->run();
            $db = $this->capsule->getConnection('tenant');
            $app->instance('files', new \Illuminate\Filesystem\Filesystem());
            $db->table('companies')->insert(['id' => 1, 'identity_document_type_id' => '6', 'number' => 'J123456789', 'name' => 'Test fiscal', 'trade_name' => 'Test fiscal', 'fiscal_environment' => 'demo', 'fiscal_emission_mode' => 'digital']);
            $db->table('configurations')->insert(['id' => 1, 'quantity_documents' => 0, 'quantity_sales_notes' => 0]);
            $establishment = $db->table('establishments')->insertGetId(['description' => 'Test fiscal', 'country_id' => 'VE', 'department_id' => '14', 'province_id' => '0229', 'district_id' => '000619', 'address' => 'Test', 'telephone' => '04121234567', 'code' => '0000']);
            $warehouse = $db->table('warehouses')->insertGetId(['establishment_id' => $establishment, 'description' => 'Test warehouse']);
            $userId = $db->table('users')->insertGetId(['name' => 'Test operator', 'email' => 'fiscal@example.test', 'password' => 'not-a-login-hash', 'type' => 'admin', 'establishment_id' => $establishment]);
            $user = \App\Models\Tenant\User::findOrFail($userId);
            $app->instance('auth', new class($user) {
                private $user;
                public function __construct($user) { $this->user = $user; }
                public function user() { return $this->user; }
                public function id() { return $this->user->id; }
                public function check() { return true; }
                public function guard($name = null) { return $this; }
            });
            $item = \App\Models\Tenant\Item::where('internal_id', 'MOCK-ITEM-VES-001')->firstOrFail();
            $db->table('item_warehouse')->insert(['item_id' => $item->id, 'warehouse_id' => $warehouse, 'stock' => 10]);
            $customer = \App\Models\Tenant\Person::where('number', '12345678')->where('identity_document_type_id', '1')->firstOrFail();
            foreach (['01' => 'FF01', '07' => 'FC01', '08' => 'FD01', '09' => 'TT01', '80' => 'NV01', 'U2' => 'AI01', 'U3' => 'AS01', 'U4' => 'AT01'] as $type => $code) {
                $seriesId = $db->table('series')->insertGetId(['establishment_id' => $establishment, 'document_type_id' => $type, 'number' => $code]);
                $db->table('series_configurations')->insert(['series_id' => $seriesId, 'document_type_id' => $type, 'series' => $code, 'number' => 100]);
            }
            $input = [
                'type' => 'invoice', 'user_id' => $userId, 'external_id' => \Illuminate\Support\Str::uuid()->toString(),
                'establishment_id' => $establishment, 'establishment' => (array) $db->table('establishments')->find($establishment),
                'state_type_id' => '01', 'group_id' => '01', 'document_type_id' => '01',
                'series' => 'FF01', 'number' => '#', 'date_of_issue' => '2026-09-10', 'time_of_issue' => '12:00:00',
                'customer_id' => $customer->id, 'customer' => $customer->toArray(), 'currency_type_id' => 'VES',
                'exchange_rate_sale' => 1, 'total_taxed' => 200, 'total_igv' => 32, 'total_taxes' => 32, 'total_value' => 200, 'total' => 232, 'additional_information' => '',
                'payments' => [['date_of_payment' => '2026-09-10', 'payment_method_type_id' => '01', 'payment' => 232, 'reference' => 'Test payment']],
                'fee' => [], 'hotel' => null, 'transport' => null, 'invoice' => ['date_of_due' => '2026-09-10', 'operation_type_id' => '0101'],
                'items' => [[
                    'item_id' => $item->id, 'item' => array_replace($item->toArray(), ['is_set' => false]), 'quantity' => 2, 'warehouse_id' => $warehouse,
                    'unit_value' => 100, 'unit_price' => 116, 'price_type_id' => '01', 'affectation_igv_type_id' => '10', 'additional_information' => '',
                    'total_base_igv' => 200, 'percentage_igv' => 16, 'total_igv' => 32, 'total_taxes' => 32, 'total_value' => 200, 'total' => 232,
                ]],
            ];
            \Illuminate\Database\Eloquent\Model::setEventDispatcher(new \Illuminate\Events\Dispatcher($app));
            \App\Models\Tenant\Document::observe(\App\Observers\DocumentObserver::class);
            $this->bootGlobalInventory($app);
            (new \Modules\Inventory\Providers\InventoryKardexServiceProvider($app))->boot();
            (new \Modules\Inventory\Providers\InventoryVoidedServiceProvider($app))->boot();
            $cash=\App\Models\Tenant\Cash::create(['user_id'=>$userId,'state'=>true,'date_opening'=>'2026-09-10','time_opening'=>'12:00:00','beginning_balance'=>'0']);
            $db->transaction(fn()=>\App\Services\Payments\CashLedger::open($cash,[]));
            $first = (new \App\CoreFacturalo\Facturalo())->save($input)->getDocument();
            self::assertSame('FF01-00000100', $first->number_full);
            $input['external_id'] = \Illuminate\Support\Str::uuid()->toString();
            $second = (new \App\CoreFacturalo\Facturalo())->save($input)->getDocument();
            self::assertSame('FF01-00000101', $second->number_full);
            self::assertSame(2, $db->table('document_payments')->count());
            self::assertEquals(464, $db->table('document_payments')->sum('payment'));
            self::assertEquals(6, $db->table('item_warehouse')->where('item_id', $item->id)->where('warehouse_id', $warehouse)->value('stock'));
            $first->forceFill(['control_number'=>'00-00000100'])->save();
            $first->emission()->update(['status'=>'confirmed','control_number'=>$first->control_number]);
            $first->unsetRelation('emission');
            foreach (['07' => ['credit', 'FC01'], '08' => ['debit', 'FD01']] as $type => [$kind, $code]) {
                $note = array_replace($input, ['external_id' => \Illuminate\Support\Str::uuid()->toString(), 'document_type_id' => $type, 'type' => $kind, 'series' => $code,
                    'note_creation_key'=>(string) \Illuminate\Support\Str::uuid(), 'payments'=>[],
                    'items'=>[['affected_document_item_id'=>$first->items->first()->id,'adjustment_amount'=>11.60]],
                    'note' => ['affected_document_id' => $first->id, 'note_type' => $kind, 'note_credit_type_id'=>$type === '07' ? '09':null, 'note_debit_type_id'=>$type === '08' ? '02':null, 'note_description' => 'Nota de prueba']]);
                $document = (new \App\CoreFacturalo\Facturalo())->save($note)->getDocument();
                self::assertSame($code . '-00000100', $document->number_full);
                self::assertSame($first->id, $document->note->affected_document_id);
            }
            $order = array_replace($input, ['external_id' => \Illuminate\Support\Str::uuid()->toString(), 'type' => 'dispatch', 'ubl_version' => '2.0', 'document_type_id' => '09', 'series' => 'TT01', 'id' => null,
                'date_of_shipping' => '2026-09-10', 'transshipment_indicator' => false, 'unit_type_id' => 'KG', 'total_weight' => 1, 'discount_stock' => true]);
            $dispatch = (new \App\CoreFacturalo\Facturalo())->save($order)->getDocument();
            self::assertSame('TT01-00000100', $dispatch->number_full);
            self::assertNull($dispatch->fiscal_identity['control_number']);
            self::assertSame('00-00000100',$first->fiscal_identity['control_number']);
            $second->setAttribute('control_number', '00-00000021');
            $second->save();
            self::assertSame('00-00000021', \App\Services\Fiscal\FiscalPdfData::forDocument($second)['control_number']);
            self::assertSame('FF01-00000100', $first->number_full);
            // Exercise the commercial NV input path, not a fake counter subject.
            $saleInput = array_replace($input, ['id' => null, 'series' => 'NV01', 'document_type_id' => '80', 'prefix' => 'NV']);
            $saleController = new \App\Http\Controllers\Tenant\SaleNoteController();
            $saleNote = \App\Models\Tenant\SaleNote::create($saleController->mergeData($saleInput));
            self::assertSame('NV01-00000100', $saleNote->number_full);
            foreach ($input['items'] as $row) $saleNote->items()->create($row);
            $globalMovement = \App\Models\Tenant\Kardex::where('sale_note_id', $saleNote->id)->sole();
            $warehouseMovement = $saleNote->inventory_kardex()->sole();
            self::assertTrue($globalMovement->exactValue('quantity')->isEqualTo('2'));
            self::assertTrue($warehouseMovement->exactValue('quantity')->isEqualTo('-2'));
            self::assertSame((int) $warehouse, (int) $warehouseMovement->warehouse_id);
            $saleNext = \App\Models\Tenant\SaleNote::create($saleController->mergeData($saleInput));
            self::assertSame('NV01-00000101', $saleNext->number_full);
            $stock = $db->table('item_warehouse')->where('item_id', $item->id)->where('warehouse_id', $warehouse)->value('stock');
            foreach (['sale_note_id' => $saleNote->id, 'dispatch_id' => $dispatch->id] as $source => $sourceId) {
                $convertedInput = array_replace($input, ['external_id' => \Illuminate\Support\Str::uuid()->toString(), $source => $sourceId]);
                $converted = (new \App\CoreFacturalo\Facturalo())->save($convertedInput)->getDocument();
                self::assertSame($sourceId, $converted->getAttribute($source));
                self::assertSame($source === 'sale_note_id' ? 102 : 103, $converted->number);
                self::assertNull($converted->fiscal_identity['control_number']);
                self::assertEquals($stock, $db->table('item_warehouse')->where('item_id', $item->id)->where('warehouse_id', $warehouse)->value('stock'));
                if ($source === 'sale_note_id') {
                    // Facturalo vincula la nota dentro de la misma transacción de la factura.
                    self::assertSame($converted->id, $saleNote->fresh()->document_id);
                }
            }
            \Modules\Inventory\Models\Guide::observe(\Modules\Inventory\Observers\GuideObserver::class);
            \Modules\Inventory\Models\InventoryTransfer::observe(\Modules\Inventory\Observers\InventoryTransferObserver::class);
            foreach (['U2' => 'AI01', 'U3' => 'AS01'] as $type => $code) {
                $transactionId = $db->table('inventory_transactions')->where('type', $type === 'U2' ? 'input' : 'output')->value('id');
                $guide = \Modules\Inventory\Models\Guide::create(['document_type_id' => $type, 'series' => $code, 'number' => '#',
                    'warehouse_id' => $warehouse, 'date_of_issue' => '2026-09-10', 'time_of_issue' => '12:00:00', 'inventory_transaction_id' => $transactionId]);
                self::assertSame(100, $guide->number);
            }
            $transfer = \Modules\Inventory\Models\InventoryTransfer::create(['document_type_id' => 'U4', 'series' => 'AT01', 'number' => '#',
                'warehouse_id' => $warehouse, 'warehouse_destination_id' => $warehouse, 'quantity' => 1]);
            self::assertSame(100, $transfer->number);
            // Real commercial persistence with blank series in two branches.
            $secondBranch = $db->table('establishments')->insertGetId(['description' => 'Second branch', 'country_id' => 'VE', 'department_id' => '14', 'province_id' => '0229', 'district_id' => '000619', 'address' => 'Test', 'telephone' => '04121234567', 'code' => '0001']);
            $secondWarehouse = $db->table('warehouses')->insertGetId(['establishment_id' => $secondBranch, 'description' => 'Second warehouse']);
            $db->table('item_warehouse')->insert(['item_id' => $item->id, 'warehouse_id' => $secondWarehouse, 'stock' => 100]);
            $filenames = [];
            foreach ([$establishment => $warehouse, $secondBranch => $secondWarehouse] as $branch => $originWarehouse) {
                foreach (['01', '07', '08', '09', '80', 'U2', 'U3', 'U4'] as $type) {
                    $db->table('series')->insert(['establishment_id' => $branch, 'document_type_id' => $type, 'number' => '']);
                }
                $blankInput = array_replace($input, ['establishment_id' => $branch, 'series' => '', 'external_id' => \Illuminate\Support\Str::uuid()->toString()]);
                $blankInput['items'][0]['warehouse_id'] = $originWarehouse;
                $blankFirst = (new \App\CoreFacturalo\Facturalo())->save($blankInput)->getDocument();
                $blankInput['external_id'] = \Illuminate\Support\Str::uuid()->toString();
                $blankSecond = (new \App\CoreFacturalo\Facturalo())->save($blankInput)->getDocument();
                self::assertSame('00000001', $blankFirst->number_full);
                self::assertSame('00000002', $blankSecond->number_full);
                self::assertSame('', $blankFirst->series);
                self::assertNull($blankFirst->control_number);
                $blankFirst->forceFill(['control_number'=>'00-'.str_pad((string)(1000+$blankFirst->id),8,'0',STR_PAD_LEFT)])->save();
                $blankFirst->emission()->update(['status'=>'confirmed','control_number'=>$blankFirst->control_number]);
                $blankFirst->unsetRelation('emission');
                $filenames[] = $blankFirst->filename;
                foreach (['07' => 'credit', '08' => 'debit'] as $type => $kind) {
                    $blankNote = array_replace($blankInput, ['external_id' => \Illuminate\Support\Str::uuid()->toString(), 'document_type_id' => $type, 'type' => $kind,
                        'note_creation_key'=>(string)\Illuminate\Support\Str::uuid(),'payments'=>[],
                        'items'=>[['affected_document_item_id'=>$blankFirst->items->first()->id,'adjustment_amount'=>11.60]],
                        'note' => ['affected_document_id' => $blankFirst->id, 'note_type'=>$kind,
                            'note_credit_type_id'=>$type === '07' ? '09':null,'note_debit_type_id'=>$type === '08' ? '02':null,'note_description' => 'Blank series note']]);
                    $savedNote = (new \App\CoreFacturalo\Facturalo())->save($blankNote)->getDocument();
                    self::assertSame('00000001', $savedNote->number_full);
                    self::assertSame($blankFirst->id, $savedNote->note->affected_document_id);
                }
                $blankOrder = array_replace($order, ['establishment_id' => $branch, 'series' => '', 'external_id' => \Illuminate\Support\Str::uuid()->toString(), 'discount_stock' => false]);
                $blankOrder['items'][0]['warehouse_id'] = $originWarehouse;
                self::assertSame('00000001', (new \App\CoreFacturalo\Facturalo())->save($blankOrder)->getDocument()->number_full);
                $blankSale = array_replace($saleInput, ['establishment_id' => $branch, 'series' => '']);
                self::assertSame('00000001', \App\Models\Tenant\SaleNote::create($saleController->mergeData($blankSale))->number_full);
                foreach (['U2', 'U3'] as $type) {
                    $transactionId = $db->table('inventory_transactions')->where('type', $type === 'U2' ? 'input' : 'output')->value('id');
                    $blankGuide = \Modules\Inventory\Models\Guide::create(['document_type_id' => $type, 'series' => '', 'number' => '#',
                        'warehouse_id' => $originWarehouse, 'date_of_issue' => '2026-09-10', 'time_of_issue' => '12:00:00', 'inventory_transaction_id' => $transactionId]);
                    self::assertSame($branch, (int) $blankGuide->establishment_id);
                    self::assertSame('00000001', $blankGuide->number_full);
                }
                $blankTransfer = \Modules\Inventory\Models\InventoryTransfer::create(['document_type_id' => 'U4', 'series' => '', 'number' => '#',
                    'warehouse_id' => $originWarehouse, 'warehouse_destination_id' => $originWarehouse, 'quantity' => 1]);
                self::assertSame($branch, (int) $blankTransfer->establishment_id);
                self::assertSame('00000001', $blankTransfer->number_full);
            }
            self::assertCount(2, array_unique($filenames));
            $longCode = 'AB-CD123456789012345';
            $db->table('series')->insert(['establishment_id' => $establishment, 'document_type_id' => '01', 'number' => $longCode]);
            $longInput = array_replace($input, ['series' => $longCode, 'external_id' => \Illuminate\Support\Str::uuid()->toString()]);
            $longDocument = (new \App\CoreFacturalo\Facturalo())->save($longInput)->getDocument();
            self::assertSame($longCode.'-00000001', $longDocument->number_full);
            self::assertStringContainsString($longCode, $longDocument->filename);
            self::assertSame($longDocument->id, \App\Models\Tenant\Document::where('establishment_id', $establishment)->where('series', $longCode)->where('number', 1)->value('id'));
            $this->assertVenezuelaFiscalPersistence($db, $input);

        } finally {
            $db->rollBack();
            \Illuminate\Database\Eloquent\Model::setEventDispatcher($previousDispatcher);
            \Illuminate\Database\Eloquent\Model::clearBootedModels();
            app()->instance('auth', $previousAuth);
        }
    }

    // ######## INICIO PERSISTENCIA FISCAL VENEZUELA ########
    private function assertVenezuelaFiscalPersistence(Connection $db, array $input): void
    {
        $service = \App\Services\Fiscal\FiscalDocumentPersistence::class;
        $customer = \App\Models\Tenant\Person::findOrFail($input['customer_id']);
        $customer->number = 'V-12345678'; $customer->save();
        \Tests\Support\SpeProfiles::versions($db, 1, $customer->id);
        $input['customer'] = $customer->toArray();
        $input['external_id'] = (string) \Illuminate\Support\Str::uuid();
        $input['payments'] = [];
        $document = (new \App\CoreFacturalo\Facturalo())->save($input)->getDocument();
        self::assertEquals(232, $document->balance);
        self::assertSame('Test fiscal', $document->issuer['name']);
        self::assertSame('G', $document->items->first()->iva_rate['code']);
        self::assertEquals(32, $document->taxes()->where('tax_kind','IVA')->sum('amount'));
        $document->guarantee_fund()->create(['amount'=>10,'base'=>200,'percentage'=>5]);
        $retention = ['tax_kind'=>'IVA','voucher_number'=>'20260900000001','voucher_date'=>'2026-09-10','agent_id'=>$customer->id,
            'base'=>32,'percentage'=>75,'amount'=>24,'currency_type_id'=>'VES','exchange_rate'=>1];
        (new \App\Services\Fiscal\HkaEmissionPreparation)->setControl($document, '00-20');
        $document->emission()->update(['status'=>'confirmed','payload'=>['documentoElectronico'=>['encabezado'=>['identificacionDocumento'=>['tipoDocumento'=>'01','serie'=>$document->series,'numeroDocumento'=>(string)$document->number,'moneda'=>$document->currency_type_id]]]]]);
        $document=$document->fresh();
        $retention += \Tests\Support\SpeProfiles::voucher($document);
        $service::retention($document,$retention);
        $service::retention($document,array_replace($retention,['tax_kind'=>'ISLR','operation_key'=>(string)\Illuminate\Support\Str::uuid(),'voucher_number'=>'TEST-ISLR-1',
            'concept_id'=>'055','base'=>200,'percentage'=>2,'amount'=>4]));
        self::assertEquals(194,$document->fresh()->balance);
        self::assertCount(2,$document->fresh()->received_retentions);
        $retainedEdit = array_replace($input, ['id' => $document->id, 'series' => $document->series, 'number' => $document->number, 'payments' => []]);
        $retentionBefore = $db->table('document_received_retentions')->where('document_id', $document->id)->get()->toArray();
        $fundBefore = $db->table('document_guarantee_funds')->where('document_id', $document->id)->get()->toArray();
        try { (new \App\CoreFacturalo\Facturalo)->update($retainedEdit, $document->id); self::fail('A voucher with a real fiscal control freezes invoice editing'); }
        catch (\Illuminate\Validation\ValidationException $expected) { self::assertArrayHasKey('document',$expected->errors()); }
        self::assertEquals(194, $document->fresh()->balance);
        foreach (['customer' => ['customer_id' => $customer->id + 1], 'iva' => ['items' => [array_replace($input['items'][0], ['quantity' => 1])]]] as $changes) {
            try { (new \App\CoreFacturalo\Facturalo)->update(array_replace($retainedEdit, $changes), $document->id); self::fail('Existing retention must remain supported'); }
            catch (\Illuminate\Validation\ValidationException $expected) { self::assertNotEmpty($expected->errors()); }
        }
        self::assertEquals($retentionBefore, $db->table('document_received_retentions')->where('document_id', $document->id)->get()->toArray());
        self::assertEquals($fundBefore, $db->table('document_guarantee_funds')->where('document_id', $document->id)->get()->toArray());

        try { $service::retention($document,array_replace($retention,['operation_key'=>(string)\Illuminate\Support\Str::uuid()]));self::fail('Duplicate voucher accepted'); }
        catch (\Illuminate\Validation\ValidationException $e) { self::assertArrayHasKey('voucher_number',$e->errors()); }
        $company=\App\Models\Tenant\Company::firstOrFail();
        $company->forceFill(['igtf_enabled'=>true,'igtf_rate'=>3])->save();
        \Tests\Support\SpeProfiles::versions($db, $company->id, $input['customer_id']);
        $key=(string)\Illuminate\Support\Str::uuid();
        $paymentInput=['date_of_payment'=>'2026-09-11','payment_method_type_id'=>'10','original_amount'=>5,
            'currency_type_id'=>'USD','exchange_rate'=>10,'igtf_status'=>'subject','financial_intermediation'=>'none','operation_key'=>$key];
        $payment=$service::payment($document,$paymentInput);
        self::assertEquals(50,$payment->payment);self::assertEquals(0.15,$payment->tax_amount);
        self::assertEquals(5.15,$payment->cash_received_amount);
        $tax=\App\Models\Tenant\DocumentTax::where('document_payment_id',$payment->id)->firstOrFail();
        $note=\App\Models\Tenant\Document::findOrFail($tax->document_id);
        self::assertSame('08',$note->document_type_id);self::assertSame('IGTF',$note->note->note_debit_type_id);
        self::assertSame('98',$note->fiscal_data->transaction_type_id);
        self::assertCount(0,$note->items);self::assertEquals(0,$note->total_igv);
        self::assertEquals(1.5,$note->total);self::assertEquals(0,$note->balance);
        self::assertEquals(0,$note->payments->first()->cash_received_amount);
        self::assertEquals(144,$document->fresh()->balance);
        self::assertSame($payment->id,$service::payment($document,$paymentInput)->id);
        self::assertSame(1,\App\Models\Tenant\DocumentTax::where('document_payment_id',$payment->id)->count());
        $service::reverse($payment,'Prueba de reversión');
        self::assertEquals(194,$document->fresh()->balance);
        self::assertNotNull($payment->fresh()->reversed_at);
        self::assertSame('11',$note->fresh()->state_type_id);
        $vesOnly = count($this->invoiceTestCurrencies()) === 1;
        $fx=array_replace($input,['external_id'=>(string)\Illuminate\Support\Str::uuid(),'currency_type_id'=>$vesOnly ? 'VES' : 'USD',
            'exchange_rate_sale'=>$vesOnly ? '1' : '10.123','payments'=>[['date_of_payment'=>'2026-09-10',
                'payment_method_type_id'=>'10','payment'=>232,'igtf_status'=>'subject','financial_intermediation'=>'none']]]);
        if ($vesOnly) $fx['payments'][0] += ['currency_type_id'=>'USD','original_amount'=>'232','exchange_rate'=>'1'];
        $paid=(new \App\CoreFacturalo\Facturalo())->save($fx)->getDocument();
        self::assertEquals(238.96,$paid->total);self::assertTrue((bool)$paid->total_canceled);self::assertEquals(238.96,$paid->payments->sum('payment'));self::assertEquals(0,$paid->balance);
        self::assertEquals(6.96,$paid->taxes()->where('tax_kind','IGTF')->sum('amount'));
        self::assertSame($vesOnly ? '238.96' : '2418.99208', \App\Support\Exact\Decimal::represent($paid->currency_totals->exactValue('total')));
        self::assertEquals(232,$paid->payments->whereNull('receipt_parent_id')->first()->payment);
        $company->forceFill(['igtf_enabled'=>false])->save();
        $principal = $paid->payments->whereNull('receipt_parent_id')->first();
        self::assertSame($principal->id,$service::payment($paid,$fx['payments'][0]+[
            'operation_key'=>$principal->operation_key],true)->id);
        $company->forceFill(['igtf_enabled'=>true])->save();
        $discounted=(new \App\CoreFacturalo\Facturalo())->save(array_replace($input,[
            'external_id'=>(string)\Illuminate\Support\Str::uuid(),
            'discounts'=>[['discount_type_id'=>'02','amount'=>20,'factor'=>0.1]],
        ]))->getDocument();
        self::assertEquals(180,$discounted->total_taxed);self::assertEquals(28.8,$discounted->total_igv);self::assertEquals(208.8,$discounted->total);
        $edit=array_replace($input,['id'=>$discounted->id,'series'=>$discounted->series,'number'=>$discounted->number,'issuer'=>['name'=>'Untrusted issuer'],'currency_type_id'=>'VES','exchange_rate_sale'=>10.1234]);
        $edit['items'][0]['quantity']=3;
        (new \App\CoreFacturalo\Facturalo())->update($edit,$discounted->id);
        self::assertEquals(324.8,$discounted->fresh()->total);
        self::assertEquals(20,$discounted->fresh()->total_discount);
        self::assertSame('10.12340000',$discounted->fresh()->exchange_rate_sale);
        self::assertEquals(324.8,$discounted->fresh()->currency_totals->total);
        self::assertSame('Test fiscal',$discounted->fresh()->issuer['name']);
        self::assertSame($discounted->external_id,$discounted->fresh()->external_id);

        $withChange=(new \App\CoreFacturalo\Facturalo())->save(array_replace($input,[
            'external_id'=>(string)\Illuminate\Support\Str::uuid(),
            'payments'=>[['date_of_payment'=>'2026-09-10','payment_method_type_id'=>'01','payment'=>250]],
        ]))->getDocument();
        self::assertEquals(0,$withChange->balance);self::assertEquals(18,$withChange->payments->first()->change);
        self::assertEquals(232,$withChange->payments->first()->cash_received_amount);
        $other=(new \App\CoreFacturalo\Facturalo())->save(array_replace($input,[
            'external_id'=>(string)\Illuminate\Support\Str::uuid(),
            'taxes'=>[['code'=>'TEST_OTHER','base'=>200,'percentage'=>2]],
        ]))->getDocument();
        self::assertEquals(4,$other->taxes()->where('tax_kind','OTI')->sum('amount'));self::assertEquals(236,$other->total);
        $prepare=new \App\Services\Fiscal\HkaEmissionPreparation();
        $prepared=$prepare->prepare($paid);
        self::assertSame('prepared',$prepared->status);
        self::assertSame('01',$prepared->payload['documentoElectronico']['encabezado']['identificacionDocumento']['tipoDocumento']);
        $payload=$prepared->payload;
        $company->name='Changed company';$company->save();
        $customer->name='Changed customer';$customer->save();
        $db->table('cat_iva_rate_types')->where('id','G')->update(['percentage'=>17]);
        self::assertSame($payload,$prepare->prepare($paid->fresh())->payload);
        self::assertSame('Test fiscal',$paid->fresh()->issuer['name']);
        self::assertNotSame('Changed customer',$paid->fresh()->customer->name);
        self::assertEquals(16,$paid->items->first()->percentage_igv);
        try {$service::assertMutable($paid);self::fail('Prepared document editable');}
        catch (\Illuminate\Validation\ValidationException $e) { self::assertArrayHasKey('document',$e->errors()); }
        try {$prepare->setControl($paid,'00-21');self::fail('Duplicate control accepted');}
        catch (\Illuminate\Validation\ValidationException $e) {self::assertArrayHasKey('control_number',$e->errors());}
        $prepare->setControl($paid,'00-22');self::assertSame('00-00000022',$paid->fresh()->control_number);
        $service::reverse($paid->payments->whereNull('receipt_parent_id')->first(),'Reversión del cobro inicial');
        self::assertEquals(238.96,$paid->fresh()->balance);self::assertFalse((bool)$paid->fresh()->total_canceled);
        self::assertEquals(6.96,$paid->taxes()->where('tax_kind','IGTF')->sum('amount'));
        self::assertSame($payload,$prepare->prepare($paid->fresh())->payload);
        // Whole persistence rolls back when a post-collection validation fails.
        $count=$db->table('documents')->count();
        try {(new \App\CoreFacturalo\Facturalo())->save(array_replace($input,['external_id'=>(string)\Illuminate\Support\Str::uuid(),
            'received_retentions'=>[array_replace($retention,['amount'=>999])]]));self::fail('Invalid retention persisted');}
        catch (\Illuminate\Validation\ValidationException $e) { self::assertSame($count,$db->table('documents')->count()); }
        foreach (['total','total_igv','total_discount'] as $column) {
            self::assertSame('numeric(30,12)',\App\Database\SchemaInspector::column($db, "documents", $column)->type);
        }
        self::assertSame('numeric(18,8)',\App\Database\SchemaInspector::column($db, "documents", 'exchange_rate_sale')->type);
        foreach (['ubl_version','perception','total_unaffected','total_free','total_igv_free','retention','user_rel_subscription_plan_id'] as $column) {
            self::assertFalse($db->getSchemaBuilder()->hasColumn('documents',$column));
        }
    }
    // ######## FIN PERSISTENCIA FISCAL VENEZUELA ########

    public function test_concurrent_payments_igtf_and_retention_applications_are_serialized(): void
    {
        $db=$this->capsule->getConnection('tenant');
        $this->prepareFiscalFixtureSchema();
        $db->table('companies')->insert(['id'=>1,'identity_document_type_id'=>'6','number'=>'J-12345678-9','name'=>'Fiscal concurrency',
            'fiscal_environment'=>'demo','fiscal_emission_mode'=>'digital','igtf_enabled'=>1,'igtf_rate'=>3]);
        $db->table('configurations')->insert(['id'=>1,'quantity_documents'=>0,'quantity_sales_notes'=>0]);
        $db->table('establishments')->insert(['id'=>1,'description'=>'Concurrent','country_id'=>'VE','department_id'=>'14','province_id'=>'0229','district_id'=>'000619','address'=>'Test','telephone'=>'04121234567','code'=>'0000']);
        $db->table('users')->insert(['id'=>1,'name'=>'Admin','email'=>'concurrency@example.test','password'=>'not-a-login-hash','type'=>'admin','establishment_id'=>1]);
        $customer=$db->table('persons')->where('number','12345678')->where('identity_document_type_id','1')->first();
        $db->table('persons')->where('id',$customer->id)->update(['identity_document_type_id'=>'6','number'=>'J-23456789-0']);
        $customer=$db->table('persons')->find($customer->id);
        \Tests\Support\SpeProfiles::versions($db, 1, $customer->id);
        $db->table('series')->insert(['establishment_id'=>1,'document_type_id'=>'08','number'=>'FD01']);
        $id=$db->table('documents')->insertGetId(['user_id'=>1,'external_id'=>(string)\Illuminate\Support\Str::uuid(),'establishment_id'=>1,
            'establishment'=>json_encode(['address'=>'Test','code'=>'0000']),'issuer'=>json_encode(['name'=>'Fiscal concurrency','number'=>'J-12345678-9']),
            'fiscal_environment'=>'demo','fiscal_emission_mode'=>'digital','group_id'=>'01','state_type_id'=>'01','document_type_id'=>'01','series'=>'FF01','number'=>1,
            'date_of_issue'=>'2026-09-10','time_of_issue'=>'12:00:00','customer_id'=>$customer->id,'customer'=>json_encode((array)$customer),
            'currency_type_id'=>'VES','exchange_rate_sale'=>1,'total_value'=>100,'total'=>100]);
        $db->table('documents')->where('id',$id)->update(['control_number'=>'00-00000999']);
        $db->table('document_emissions')->insert(['document_id'=>$id,'fiscal_environment'=>'demo','operation_key'=>(string)\Illuminate\Support\Str::uuid(),'status'=>'confirmed','payload'=>json_encode(['documentoElectronico'=>['encabezado'=>['identificacionDocumento'=>['tipoDocumento'=>'01','serie'=>'FF01','numeroDocumento'=>'1','moneda'=>'VES']]]])]);
        $db->table('cash')->insert(['user_id'=>1,'state'=>true,'date_opening'=>'2026-09-10','time_opening'=>'12:00:00']);
        $key=(string)\Illuminate\Support\Str::uuid();
        $payment=['date_of_payment'=>'2026-09-10','payment_method_type_id'=>'10','currency_type_id'=>'USD','exchange_rate'=>10,
            'original_amount'=>8,'igtf_status'=>'subject','financial_intermediation'=>'none','operation_key'=>$key];
        $result=$this->runFiscalWorkers($id,'payment',array_fill(0,20,$payment));
        self::assertArrayHasKey('id',$result[0],json_encode($result));
        self::assertArrayHasKey('id',$result[1],json_encode($result));
        self::assertSame($result[0]['id'],$result[1]['id']);
        self::assertSame(1,$db->table('document_payments')->where('document_id',$id)->whereNull('receipt_parent_id')->count());
        self::assertSame(1,$db->table('document_taxes')->whereNotNull('document_payment_id')->count());
        // ######## INICIO MONEDA DEL MEDIO DE COBRO ########
        $principal=\App\Models\Tenant\DocumentPayment::findOrFail($result[0]['id']);
        $derived=\App\Models\Tenant\DocumentPayment::where('receipt_parent_id',$principal->id)->firstOrFail();
        $principalRow=\App\Services\Payments\DocumentPaymentReport::row($principal);
        $derivedRow=\App\Services\Payments\DocumentPaymentReport::row($derived);
        self::assertSame('8.24',$principalRow['payment']);
        self::assertSame('80',$principalRow['applied_payment']);
        self::assertSame('USD',$principalRow['currency_type_id']);
        self::assertSame('VES',$principalRow['document_currency_type_id']);
        self::assertSame('Aplicación de IGTF',$derivedRow['kind']);
        self::assertSame('0',$derivedRow['payment']);
        self::assertSame('2.4',$derivedRow['applied_payment']);
        // ######## FIN MONEDA DEL MEDIO DE COBRO ########
        self::assertSame(1,$db->table('documents')->where('document_type_id','08')->count());
        $p1=array_replace($payment,['operation_key'=>(string)\Illuminate\Support\Str::uuid(),'original_amount'=>1.5]);
        $p2=array_replace($p1,['operation_key'=>(string)\Illuminate\Support\Str::uuid()]);
        $result=$this->runFiscalWorkers($id,'payment',array_map(static fn ($i)=>array_replace($p1,['operation_key'=>(string)\Illuminate\Support\Str::uuid()]),range(1,20)));
        self::assertCount(1,array_filter($result,fn ($r)=>isset($r['id'])));
        self::assertEquals(95,$db->table('document_payments')->where('document_id',$id)->sum('payment'));
        self::assertSame(2,$db->table('documents')->where('document_type_id','08')->count());
        $retention=['tax_kind'=>'ISLR','voucher_number'=>'CONCURRENT-1','voucher_date'=>'2026-09-10','agent_id'=>$customer->id,
            'concept_id'=>'063','base'=>80,'percentage'=>5,'amount'=>4,'currency_type_id'=>'VES','exchange_rate'=>1];
        $retention += \Tests\Support\SpeProfiles::voucher(\App\Models\Tenant\Document::findOrFail($id));
        $result=$this->runFiscalWorkers($id,'retention',array_map(static fn ($i)=>array_replace($retention,['voucher_number'=>'CONCURRENT-'.$i]),range(1,20)));
        self::assertCount(1,array_filter($result,fn ($r)=>isset($r['id'])));
        self::assertEquals(4,$db->table('document_received_retentions')->where('document_id',$id)->sum('applied_amount'));
        $retention=array_replace($retention,['voucher_number'=>'CONCURRENT-DUPLICATE','concept_id'=>'055','base'=>50,'percentage'=>2,'amount'=>1]);
        $result=$this->runFiscalWorkers($id,'retention',array_fill(0,20,$retention));
        self::assertCount(0,array_filter($result,fn ($r)=>isset($r['id'])));
        self::assertSame(1,$db->table('document_received_retentions')->where('document_id',$id)->count());
    }

    private function runFiscalWorkers(int $documentId,string $operation,array $data): array
    {
        $db=$this->capsule->getConnection('tenant');$processes=[];
        $db->beginTransaction();
        try {
            $db->table('companies')->where('id',1)->lockForUpdate()->first();
            foreach ($data as $input) {
                $p=new \Symfony\Component\Process\Process([PHP_BINARY,dirname(__DIR__).'/Support/fiscal_payment_concurrency_worker.php']);
                $p->setInput(json_encode(['connection'=>$db->getConfig(),'document_id'=>$documentId,'operation'=>$operation,'data'=>$input],JSON_THROW_ON_ERROR));
                $p->setTimeout(90);$p->start();$processes[]=$p;
            }
            $deadline=microtime(true)+60;
            foreach ($processes as $p) {
                while (!str_contains($p->getOutput(),"READY\n")) {
                    if (!$p->isRunning() || microtime(true)>$deadline) self::fail('Fiscal worker did not reach the lock barrier: '.$p->getErrorOutput());
                    usleep(10000);
                }
                self::assertTrue($p->isRunning());
            }
            $db->commit();$results=[];
            foreach ($processes as $p) {
                self::assertSame(0,$p->wait(),$p->getErrorOutput().$p->getOutput());
                $results[]=json_decode(substr($p->getOutput(),strlen("READY\n")),true,512,JSON_THROW_ON_ERROR);
            }
            return $results;
        } finally {
            if ($db->transactionLevel()) $db->rollBack();
            foreach ($processes as $p) if ($p->isRunning()) $p->stop();
        }
    }


    public function test_special_tax_regimes_persist_settle_edit_freeze_and_emit_after_commit(): void
    {
        [$db,$app,$input]=$this->bootHkaFixture();
        $party=\App\Models\Tenant\Person::create(['type'=>'customers','identity_document_type_id'=>'1','number'=>'98765432',
            'name'=>'TITULAR SINTETICO','country_id'=>'VE','address'=>'DIRECCION DE PRUEBA','is_third_party'=>true,
            'third_party_rif'=>'V987654321','third_party_activity'=>'Bienes y servicios','enabled'=>true]);
        $count=0;$mode='confirmed';
        $app->make(\Illuminate\Http\Client\Factory::class)->fake(function($request)use($db,&$count,&$mode){
            self::assertSame(0,$db->transactionLevel());
            if(str_ends_with($request->url(),'/Autenticacion'))return \Illuminate\Support\Facades\Http::response(['token'=>'test-private','expiracion'=>date('c',time()+3600)]);
            if(str_ends_with($request->url(),'/DescargaArchivo')){
                $pdf=new \FPDF();$pdf->AddPage();$pdf->SetFont('Helvetica','',12);$pdf->Text(10,15,'Special regime DEMO');
                return \Illuminate\Support\Facades\Http::response(['codigo'=>'200','archivo'=>base64_encode($pdf->Output('S'))]);
            }
            $count++;
            if($mode==='timeout')throw new \Illuminate\Http\Client\ConnectionException('Private test message');
            if($mode==='rejected')return \Illuminate\Support\Facades\Http::response(['codigo'=>'203','validaciones'=>['1015 - Rechazo de prueba']]);
            $id=$request->data()['documentoElectronico']['encabezado']['identificacionDocumento'];
            return \Illuminate\Support\Facades\Http::response(['codigo'=>'200','resultado'=>$id+['numeroControl'=>'00-'.str_pad($id['numeroDocumento'],8,'0',STR_PAD_LEFT)]]);
        });
        $input=array_replace($input,['source_module'=>'WEB','exchange_rate_sale'=>'40.12345678','actions'=>['send_email'=>false]]);
        $case=$input+['fiscal_data'=>['special_tax_regime_id'=>1]];
        $stock=$db->table('item_warehouse')->where('item_id',$input['items'][0]['item_id'])->value('stock');
        foreach([['source_module'=>'POS'],['source_module'=>'GARAGE'],['payments'=>[]],['payment_condition_id'=>'02']] as $change){
            try{(new \App\CoreFacturalo\Facturalo)->forWebDocuments()->save(array_replace($case,$change));self::fail('Unsupported regime operation');}
            catch(\Illuminate\Validation\ValidationException $e){self::assertNotEmpty($e->errors());}
            self::assertSame(0,$db->table('documents')->count());self::assertSame(0,$count);
            self::assertEquals($stock,$db->table('item_warehouse')->where('item_id',$input['items'][0]['item_id'])->value('stock'));
        }
        $db->beginTransaction();(new \App\CoreFacturalo\Facturalo)->forWebDocuments()->save($case);$db->rollBack();self::assertSame(0,$count);
        foreach(\App\Services\SpecialTaxRegimePolicy::NAMES as $id=>$name)foreach($this->invoiceTestCurrencies() as $currency)foreach([false,true] as $third){
            $case=array_replace($input,['fiscal_data'=>['special_tax_regime_id'=>$id],'currency_type_id'=>$currency,'third_party_id'=>$third?$party->id:null,
                'external_id'=>(string)\Illuminate\Support\Str::uuid()]);
            $case['payments'][0]['payment_method_type_id']=$currency === 'USD' ? '10' : '01';
            $db->beginTransaction();$doc=(new \App\CoreFacturalo\Facturalo)->forWebDocuments()->save($case)->getDocument()->fresh();
            $prior=$count;self::assertSame('not_requested',$doc->emission->status);$db->commit();$doc=$doc->fresh();
            self::assertSame($prior+1,$count);self::assertSame('confirmed',$doc->emission->status);self::assertNotEmpty($doc->control_number);
            self::assertEquals(0,$doc->balance);self::assertEquals(232,$doc->total);self::assertSame($id,$doc->fiscal_data->special_tax_regime_id);
            $h=$doc->emission->payload['documentoElectronico']['encabezado'];
            self::assertSame($name,$h['identificacionDocumento']['regimenEspTributacion']);self::assertSame($third?'99':'01',$h['identificacionDocumento']['tipoTransaccion']);
            self::assertSame($currency,$doc->currency_type_id);self::assertSame($name,$doc->fiscal_data->catalog_snapshot['special_tax_regime_id']['description']);
            $frozen=$doc->emission->payload;
            $db->table('cat_special_tax_regime')->where('id',$id)->update(['description'=>'Nombre nuevo','active'=>0]);
            self::assertSame($frozen,$doc->fresh()->emission->payload);self::assertSame($name,\App\Services\SpecialTaxRegimePolicy::view($doc->fresh())['special_tax_regime_description']);
            $db->table('cat_special_tax_regime')->where('id',$id)->update(['description'=>$name,'active'=>1]);
        }
        self::assertSame(count(\App\Services\SpecialTaxRegimePolicy::NAMES) * count($this->invoiceTestCurrencies()) * 2, $count);
        foreach(['rejected','timeout'] as $failure){
            $mode=$failure;$failed=(new \App\CoreFacturalo\Facturalo)->forWebDocuments()->save($case)->getDocument()->fresh();
            self::assertSame($failure==='timeout'?'uncertain':'rejected',$failed->emission->status);self::assertEquals(0,$failed->balance);self::assertEquals(232,$failed->total);
        }
        $company=\App\Models\Tenant\Company::firstOrFail();$company->fiscal_credentials=null;$company->save();
        $own=array_replace($input,['external_id'=>(string)\Illuminate\Support\Str::uuid()]);
        $doc=(new \App\CoreFacturalo\Facturalo)->forWebDocuments()->save($own)->getDocument()->fresh();
        $payments=$db->table('document_payments')->where('document_id',$doc->id)->get()->toArray();$number=$doc->number;
        $edit=array_replace($own,['id'=>$doc->id,'series'=>$doc->series,'number'=>$number,'payments'=>[]]);
        foreach([1,2,null] as $regime){
            $old=$doc->emission->operation_key;$before=$count;$edit['fiscal_data']=['special_tax_regime_id'=>$regime];
            (new \App\CoreFacturalo\Facturalo)->forWebDocuments()->update($edit,$doc->id);$doc=$doc->fresh();
            self::assertNotSame($old,$doc->emission->operation_key);self::assertSame('not_requested',$doc->emission->status);self::assertSame($before,$count);
            self::assertSame($regime,$doc->fiscal_data->special_tax_regime_id);self::assertSame($number,$doc->number);
            self::assertEquals($payments,$db->table('document_payments')->where('document_id',$doc->id)->get()->toArray());
        }
        $edit['fiscal_data']=['special_tax_regime_id'=>3];(new \App\CoreFacturalo\Facturalo)->forWebDocuments()->update($edit,$doc->id);$doc=$doc->fresh();
        $bad=$edit;$bad['fiscal_data']=['special_tax_regime_id'=>null];$bad['items'][0]['quantity']=3;
        try{(new \App\CoreFacturalo\Facturalo)->forWebDocuments()->update($bad,$doc->id);self::fail('Removing regime does not bypass settlement');}
        catch(\Illuminate\Validation\ValidationException $e){self::assertArrayHasKey('payments',$e->errors());}
        self::assertSame(3,$doc->fresh()->fiscal_data->special_tax_regime_id);
        try{\App\Services\SpecialTaxRegimePolicy::assertInput(['document_type_id'=>'07','affected_document_id'=>$doc->id]);self::fail('Regime notes are excluded');}
        catch(\Illuminate\Validation\ValidationException $e){self::assertArrayHasKey('note',$e->errors());}
        $company->forceFill(['igtf_enabled'=>true,'igtf_rate'=>3])->save();
        \Tests\Support\SpeProfiles::versions($db, $company->id, $input['customer_id']);
        try{\App\Services\Fiscal\FiscalDocumentPersistence::payment($doc,['date_of_payment'=>'2026-09-10','payment_method_type_id'=>'10','original_amount'=>1,'currency_type_id'=>'USD','exchange_rate'=>'40.12345678','igtf_status'=>'subject','financial_intermediation'=>'none']);self::fail('Later IGTF requires excluded note');}
        catch(\Illuminate\Validation\ValidationException $e){self::assertArrayHasKey('payment',$e->errors());}
        (new \App\Services\Fiscal\HkaEmissionPreparation)->prepare($doc);$doc=$doc->fresh();$preparedKey=$doc->emission->operation_key;
        $edit['fiscal_data']=['special_tax_regime_id'=>4];(new \App\CoreFacturalo\Facturalo)->forWebDocuments()->update($edit,$doc->id);$doc=$doc->fresh();
        self::assertNotSame($preparedKey,$doc->emission->operation_key);self::assertNull($doc->emission->payload);
        $company->fiscal_credentials=json_encode(['usuario'=>'test-user','clave'=>'test-secret']);$company->save();$mode='confirmed';
        $log=tempnam(sys_get_temp_dir(),'sab_regime_race_');
        try{
            $edit['fiscal_data']=['special_tax_regime_id'=>5];
            $results=$this->runFiscalWorkers($doc->id,'hka-edit-race',[
                ['action'=>'edit','edit'=>$edit,'web_export'=>true],['action'=>'send','log'=>$log]]);
            self::assertTrue(($results[0]['status']??null)==='edited'||isset($results[0]['rejected']));
            $after=$doc->fresh();if($after->emission->status==='not_requested')app(\App\Services\Fiscal\HkaEmission::class)->send($after);
            $after=$after->fresh();self::assertSame('confirmed',$after->emission->status);
            self::assertSame($after->fiscal_data->catalog_snapshot['special_tax_regime_id']['description'],$after->emission->payload['documentoElectronico']['encabezado']['identificacionDocumento']['regimenEspTributacion']);
            self::assertEquals($payments,$db->table('document_payments')->where('document_id',$doc->id)->get()->toArray());
        }finally{unlink($log);}
    }

    public function test_third_party_invoices_persist_freeze_edit_and_emit_after_commit(): void
    {
        [$db,$app,$input]=$this->bootHkaFixture();
        $parties=[];
        foreach (['J','V'] as $index=>$letter) {
            $parties[]=\App\Models\Tenant\Person::create(['type'=>'customers','identity_document_type_id'=>$letter==='J'?'6':'1',
                'number'=>$letter==='J'?'987654321':'23456789','name'=>'TERCERO '.$letter,'country_id'=>'VE','address'=>'DIRECCION DEL TERCERO '.$letter,
                'is_third_party'=>true,'third_party_rif'=>$letter.'987654321','third_party_activity'=>'Venta y servicios de prueba','enabled'=>true]);
        }
        $transmissions=0;
        $app->make(\Illuminate\Http\Client\Factory::class)->fake(function($request)use($db,&$transmissions){
            self::assertSame(0,$db->transactionLevel());
            if(str_ends_with($request->url(),'/Autenticacion'))return \Illuminate\Support\Facades\Http::response(['token'=>'private-test-token','expiracion'=>date('c',time()+3600)]);
            if(str_ends_with($request->url(),'/DescargaArchivo')){
                $pdf=new \FPDF();$pdf->AddPage();$pdf->SetFont('Helvetica','',12);$pdf->Text(10,15,'Third party invoice DEMO');
                return \Illuminate\Support\Facades\Http::response(['codigo'=>'200','archivo'=>base64_encode($pdf->Output('S'))]);
            }
            self::assertStringEndsWith('/Emision',$request->url());$transmissions++;
            $h=$request->data()['documentoElectronico']['encabezado'];
            self::assertSame('99',$h['identificacionDocumento']['tipoTransaccion']);
            self::assertNotSame($h['comprador']['razonSocial'],$h['tercero']['razonSocial']);
            return \Illuminate\Support\Facades\Http::response(['codigo'=>'200','resultado'=>$h['identificacionDocumento']+['numeroControl'=>'00-'.str_pad($h['identificacionDocumento']['numeroDocumento'],8,'0',STR_PAD_LEFT)]]);
        });
        $third=array_replace($input,['third_party_id'=>$parties[0]->id,'exchange_rate_sale'=>'40.12345678']);
        $beforeStock=$db->table('item_warehouse')->where('item_id',$input['items'][0]['item_id'])->value('stock');
        $db->beginTransaction();(new \App\CoreFacturalo\Facturalo)->forWebDocuments()->save($third);self::assertSame(0,$transmissions);$db->rollBack();
        self::assertSame(0,$db->table('documents')->count());
        self::assertEquals($beforeStock,$db->table('item_warehouse')->where('item_id',$input['items'][0]['item_id'])->value('stock'));
        foreach($parties as $party)foreach($this->invoiceTestCurrencies() as $currency){
            $case=array_replace($third,['third_party_id'=>$party->id,'currency_type_id'=>$currency,'external_id'=>(string)\Illuminate\Support\Str::uuid()]);
            $case['payments'][0]['payment_method_type_id']=$currency === 'USD' ? '10' : '01';
            $db->beginTransaction();$doc=(new \App\CoreFacturalo\Facturalo)->forWebDocuments()->save($case)->getDocument()->fresh();
            $prior=$transmissions;$db->commit();$doc=$doc->fresh();
            self::assertSame($prior+1,$transmissions);self::assertSame('confirmed',$doc->emission->status);
            self::assertSame('99',$doc->fiscal_data->transaction_type_id);self::assertSame($party->id,$doc->fiscal_data->third_party_person_id);
            self::assertEquals(232,$doc->total);self::assertEquals(0,$doc->balance);
            self::assertSame($input['customer_id'],$doc->customer_id);self::assertSame('01',$doc->document_type_id);
            $payload=$doc->emission->payload;$saved=$doc->fiscal_data->third_party;
            $party->update(['address'=>'DIRECCION NUEVA EN FICHA']);
            self::assertEquals($saved,$doc->fresh()->fiscal_data->third_party);self::assertEquals($payload,$doc->fresh()->emission->payload);
            try{$db->table('persons')->where('id',$party->id)->delete();self::fail('A used titular cannot be physically deleted');}
            catch(\Illuminate\Database\QueryException $e){self::assertSame('23503',$e->getCode());}
        }
        $frozenParty=$doc->fiscal_data->third_party;$frozenPayload=$doc->emission->payload;
        foreach([['is_third_party'=>false],['is_third_party'=>true,'enabled'=>false]] as $change){
            $parties[1]->update($change);
            try{\App\Services\ThirdPartyInvoicePolicy::resolveInput(array_replace($third,['third_party_id'=>$parties[1]->id]),true);self::fail('An unclassified or disabled titular must not be selected');}
            catch(\Illuminate\Validation\ValidationException $e){self::assertArrayHasKey('third_party_id',$e->errors());}
            self::assertEquals($frozenParty,$doc->fresh()->fiscal_data->third_party);self::assertEquals($frozenPayload,$doc->fresh()->emission->payload);
        }
        $parties[1]->update(['is_third_party'=>true,'enabled'=>true]);
        $company=\App\Models\Tenant\Company::firstOrFail();$company->fiscal_credentials=null;$company->save();
        $doc=(new \App\CoreFacturalo\Facturalo)->forWebDocuments()->save($third)->getDocument()->fresh();
        (new \App\Services\Fiscal\HkaEmissionPreparation)->prepare($doc);$doc=$doc->fresh();$oldKey=$doc->emission->operation_key;
        $payments=$db->table('document_payments')->where('document_id',$doc->id)->get()->toArray();
        $edit=array_replace($third,['id'=>$doc->id,'series'=>$doc->series,'number'=>$doc->number,'third_party_id'=>$parties[1]->id,'payments'=>[]]);
        (new \App\CoreFacturalo\Facturalo)->forWebDocuments()->update($edit,$doc->id);$doc=$doc->fresh();
        self::assertSame($parties[1]->id,$doc->fiscal_data->third_party_person_id);self::assertNotSame($oldKey,$doc->emission->operation_key);
        self::assertSame('not_requested',$doc->emission->status);self::assertEquals($payments,$db->table('document_payments')->where('document_id',$doc->id)->get()->toArray());
        $unsentKey=$doc->emission->operation_key;
        (new \App\CoreFacturalo\Facturalo)->forWebDocuments()->update($edit,$doc->id);$doc=$doc->fresh();
        self::assertNotSame($unsentKey,$doc->emission->operation_key);self::assertSame('not_requested',$doc->emission->status);
        self::assertEquals($payments,$db->table('document_payments')->where('document_id',$doc->id)->get()->toArray());
        try{(new \App\CoreFacturalo\Facturalo)->forWebDocuments()->update(array_replace($edit,['third_party_id'=>null]),$doc->id);self::fail('Category conversion must fail');}
        catch(\Illuminate\Validation\ValidationException $e){self::assertArrayHasKey('third_party_id',$e->errors());}
        try{\App\Services\ThirdPartyInvoicePolicy::assertInput(['document_type_id'=>'07','affected_document_id'=>$doc->id]);self::fail('Third-party notes must fail');}
        catch(\Illuminate\Validation\ValidationException $e){self::assertArrayHasKey('note',$e->errors());}
        $count=$db->table('documents')->count();$stock=$db->table('item_warehouse')->where('item_id',$input['items'][0]['item_id'])->value('stock');
        $unpaid=$third;$unpaid['payments']=[];
        try{(new \App\CoreFacturalo\Facturalo)->forWebDocuments()->save($unpaid);self::fail('An unpaid invoice must roll back');}
        catch(\Illuminate\Validation\ValidationException $e){self::assertArrayHasKey('payments',$e->errors());}
        self::assertSame($count,$db->table('documents')->count());self::assertEquals($stock,$db->table('item_warehouse')->where('item_id',$input['items'][0]['item_id'])->value('stock'));
        $company->igtf_enabled=true;$company->igtf_rate=3;$company->save();
        \Tests\Support\SpeProfiles::versions($db, $company->id, $input['customer_id']);
        $vesOnly = count($this->invoiceTestCurrencies()) === 1;
        $igtfInput=array_replace($third,['currency_type_id'=>$vesOnly ? 'VES' : 'USD']);
        $igtfInput['payments'][0]['payment_method_type_id']='10';
        $igtfInput['payments'][0]['igtf_status']='subject';
        $igtfInput['payments'][0]['financial_intermediation']='none';
        if ($vesOnly) {
            $igtfInput['exchange_rate_sale']='1';
            $igtfInput['payments'][0] += ['currency_type_id'=>'USD','original_amount'=>'232','exchange_rate'=>'1'];
        }
        $igtfDoc=(new \App\CoreFacturalo\Facturalo)->forWebDocuments()->save($igtfInput)->getDocument()->fresh();
        self::assertEquals(238.96,$igtfDoc->total);self::assertEquals(0,$igtfDoc->balance);
        $prepared=(new \App\Services\Fiscal\HkaEmissionPreparation)->prepare($igtfDoc);
        self::assertContains('IGTF',array_column($prepared->payload['documentoElectronico']['encabezado']['totales']['impuestosSubtotal'],'codigoTotalImp'));
        $notesBefore=$db->table('documents')->where('document_type_id','08')->count();
        try{\App\Services\Fiscal\FiscalDocumentPersistence::payment($igtfDoc,['date_of_payment'=>'2026-09-10','payment_method_type_id'=>'10','original_amount'=>1,'currency_type_id'=>'USD','exchange_rate'=>'40.12345678','igtf_status'=>'subject','financial_intermediation'=>'none']);self::fail('A later IGTF debit note is outside this stage');}
        catch(\Illuminate\Validation\ValidationException $e){self::assertArrayHasKey('payment',$e->errors());}
        self::assertSame($notesBefore,$db->table('documents')->where('document_type_id','08')->count());
        (new \App\Services\Fiscal\HkaEmissionPreparation)->prepare($doc);
        $company->fiscal_credentials=json_encode(['usuario'=>'test-user','clave'=>'test-secret']);$company->save();
        $raceEdit=array_replace($edit,['third_party_id'=>$parties[0]->id]);$log=tempnam(sys_get_temp_dir(),'sab_third_party_race_');
        try{
            $results=$this->runFiscalWorkers($doc->id,'hka-edit-race',[
                ['action'=>'edit','edit'=>$raceEdit,'web_export'=>true],['action'=>'send','log'=>$log]]);
            self::assertTrue(($results[0]['status']??null)==='edited'||isset($results[0]['rejected']));
            $after=$doc->fresh();if($after->emission->status==='not_requested')app(\App\Services\Fiscal\HkaEmission::class)->send($after);
            $after=$after->fresh();self::assertSame('confirmed',$after->emission->status);
            self::assertSame('99',$after->emission->payload['documentoElectronico']['encabezado']['identificacionDocumento']['tipoTransaccion']);
            self::assertSame($after->fiscal_data->third_party['name'],$after->emission->payload['documentoElectronico']['encabezado']['tercero']['razonSocial']);
            self::assertEquals($payments,$db->table('document_payments')->where('document_id',$doc->id)->get()->toArray());
        }finally{unlink($log);}
    }

    public function test_export_invoices_persist_freeze_edit_and_emit_after_commit(): void
    {
        [$db, $app, $input] = $this->bootHkaFixture();
        $foreign = \App\Models\Tenant\Person::create(['type'=>'customers', 'identity_document_type_id'=>'ND', 'external_identity_type'=>'NIT',
            'number'=>'900123-4', 'name'=>'Foreign buyer', 'country_id'=>'CO', 'address'=>'Bogotá, Colombia', 'email'=>'export@example.test', 'telephone'=>'+57123456789']);
        $input = array_replace($input, ['customer_id'=>$foreign->id, 'customer'=>\App\CoreFacturalo\Requests\Inputs\Common\PersonInput::set($foreign->id),
            'source_module'=>'WEB', 'payments'=>[], 'payment_condition_id'=>'02', 'exchange_rate_sale'=>'40.12345678',
            'discounts'=>[['discount_type_id'=>'03', 'factor'=>0.05, 'amount'=>10, 'base'=>200]],
            'charges'=>[['charge_type_id'=>'62', 'factor'=>0.025, 'amount'=>5, 'base'=>200]], 'actions'=>['send_email'=>false]]);
        $input['items'][0] = array_replace($input['items'][0], ['affectation_igv_type_id'=>'EXP', 'unit_price'=>100]);
        $ordinaryCustomer = $db->table('persons')->where('number','12345678')->where('identity_document_type_id','1')->value('id');
        $exportInput = array_replace_recursive($input,['invoice'=>['operation_type_id'=>'0201']]);
        foreach (['API'=>['web'=>false], 'POS'=>['changes'=>['source_module'=>'POS']], 'Domestic buyer'=>['changes'=>['customer_id'=>$ordinaryCustomer]],
            'Mixed tax'=>['changes'=>['items'=>[0=>['affectation_igv_type_id'=>'20']]]]] as $label=>$case) {
            $invalid = array_replace_recursive($exportInput,$case['changes']??[]);
            try {
                $engine = new \App\CoreFacturalo\Facturalo;
                if ($case['web']??true) $engine->forWebDocuments();
                $engine->save($invalid); self::fail($label.' must reject before persistence');
            } catch (\Illuminate\Validation\ValidationException $expected) { self::assertNotEmpty($expected->errors()); }
            self::assertSame(0,$db->table('documents')->count());
        }
        try {
            $db->table('persons')->insert($foreign->only(['type','identity_document_type_id','external_identity_type','number','name','country_id','address']));
            self::fail('Duplicate foreign identity must be rejected by PostgreSQL');
        } catch (\Illuminate\Database\QueryException $expected) { self::assertSame('23505',$expected->getCode()); }
        $transmissions = 0;
        $http = $app->make(\Illuminate\Http\Client\Factory::class);
        $http->fake(function ($request) use ($db, &$transmissions) {
            self::assertSame(0, $db->transactionLevel());
            if (str_ends_with($request->url(), '/Autenticacion')) return \Illuminate\Support\Facades\Http::response(['token'=>'private-test-token', 'expiracion'=>date('c', time()+3600)]);
            if (str_ends_with($request->url(), '/DescargaArchivo')) {
                $pdf = new \FPDF(); $pdf->AddPage(); $pdf->SetFont('Helvetica','',12); $pdf->Text(10,15,'Export invoice DEMO');
                return \Illuminate\Support\Facades\Http::response(['codigo'=>'200', 'archivo'=>base64_encode($pdf->Output('S'))]);
            }
            self::assertStringEndsWith('/Emision', $request->url());
            $transmissions++;
            $id = $request->data()['documentoElectronico']['encabezado']['identificacionDocumento'];
            return \Illuminate\Support\Facades\Http::response(['codigo'=>'200', 'resultado'=>$id+['numeroControl'=>'00-'.str_pad($id['numeroDocumento'],8,'0',STR_PAD_LEFT)]]);
        });
        $db->beginTransaction();
        $rolledBack = array_replace_recursive($input, ['invoice'=>['operation_type_id'=>'0201']]);
        (new \App\CoreFacturalo\Facturalo)->forWebDocuments()->save($rolledBack);
        self::assertSame(0, $transmissions);
        $db->rollBack();
        self::assertSame(0, $db->table('documents')->count());
        foreach (\App\Services\ExportInvoicePolicy::INCOTERMS as $operation=>$term) foreach (array_reverse($this->invoiceTestCurrencies()) as $currency) {
            $case = array_replace_recursive($input, ['invoice'=>['operation_type_id'=>$operation], 'currency_type_id'=>$currency,
                'external_id'=>(string) \Illuminate\Support\Str::uuid()]);
            $db->beginTransaction();
            $document = (new \App\CoreFacturalo\Facturalo)->forWebDocuments()->save($case)->getDocument()->fresh();
            self::assertEquals(195, $document->total_exportation);
            self::assertEquals(0, $document->total_exonerated);
            self::assertEquals(0, $document->total_igv);
            self::assertEquals(195, $document->total);
            // ######## INICIO CALCULOS EXACTOS SAB ########
            // Stored equivalence retains 195 * 40.12345678; cents belong to the provider copy.
            self::assertSame($currency==='USD' ? '7824.0740721' : '195',
                \App\Support\Exact\Decimal::represent($document->currency_totals->exactValue('exportation')));
            // ######## FIN CALCULOS EXACTOS SAB ########
            self::assertSame('CO', $document->customer->country_id);
            self::assertSame('NIT', $document->customer->hka_identity_code);
            $before = $transmissions;
            $db->commit();
            $document = $document->fresh();
            self::assertSame($before+1, $transmissions);
            self::assertSame('confirmed', $document->emission->status, json_encode($document->emission->response));
            self::assertNotEmpty($document->control_number);
            self::assertSame($term, $document->emission->payload['documentoElectronico']['encabezado']['identificacionDocumento']['tipoDeVenta']);
            $payload = $document->emission->payload;
            $foreign->update(['address'=>'Changed in master data']);
            $db->table('cat_operation_types')->where('id',$operation)->update(['description'=>'Changed catalogue']);
            self::assertSame($payload, $document->fresh()->emission->payload);
            app(\App\Services\Fiscal\HkaEmission::class)->send($document);
            self::assertSame($before+1, $transmissions);
            self::assertFalse(isset($document->emission->response['mail']));
        }
        foreach (['07','08'] as $noteType) {
            try {
                \App\Services\ExportInvoicePolicy::assertInput(['document_type_id'=>$noteType,'affected_document_id'=>$document->id]);
                self::fail('Export notes remain disabled');
            } catch (\Illuminate\Validation\ValidationException $expected) { self::assertArrayHasKey('note',$expected->errors()); }
        }
        $company = \App\Models\Tenant\Company::firstOrFail();
        foreach ([['fiscal_environment'=>'production'],['fiscal_emission_mode'=>'free_form']] as $unavailable) {
            $company->forceFill($unavailable)->save();
            try {
                \App\Services\ExportInvoicePolicy::assertInput($exportInput,true); self::fail('Only digital DEMO supports export');
            } catch (\Illuminate\Validation\ValidationException $expected) { self::assertArrayHasKey('operation_type_id',$expected->errors()); }
            $company->forceFill(['fiscal_environment'=>'demo','fiscal_emission_mode'=>'digital'])->save();
        }
        $company->forceFill(['igtf_enabled'=>true,'igtf_rate'=>3])->save();
        \Tests\Support\SpeProfiles::versions($db, $company->id, $input['customer_id']);
        $paymentCount=$db->table('document_payments')->count();
        try {
            \App\Services\Fiscal\FiscalDocumentPersistence::payment($document,['date_of_payment'=>'2026-09-10','payment_method_type_id'=>'10',
                'currency_type_id'=>'USD','original_amount'=>1,'exchange_rate'=>'40.12345678','igtf_status'=>'subject','financial_intermediation'=>'none']);
            self::fail('A post-emission export IGTF note is outside this stage');
        } catch (\Illuminate\Validation\ValidationException $expected) { self::assertArrayHasKey('payment',$expected->errors()); }
        self::assertSame($paymentCount,$db->table('document_payments')->count());
        $company->forceFill(['igtf_enabled'=>false])->save();
        // Before transmission an edit keeps collections but refreshes the frozen operation.
        $company = \App\Models\Tenant\Company::firstOrFail(); $credentials = $company->fiscal_credentials;
        $company->fiscal_credentials = null; $company->save();
        $case = array_replace_recursive($input, ['invoice'=>['operation_type_id'=>'0201'], 'external_id'=>(string) \Illuminate\Support\Str::uuid(),
            'discounts'=>[], 'charges'=>[], 'payments'=>[['date_of_payment'=>'2026-09-10','payment_method_type_id'=>'01','payment'=>100,'reference'=>'Part payment']]]);
        $case['discounts']=[]; $case['charges']=[];
        $document = (new \App\CoreFacturalo\Facturalo)->forWebDocuments()->save($case)->getDocument()->fresh();
        $payments = $document->payments->toArray();
        (new \App\Services\Fiscal\HkaEmissionPreparation)->prepare($document);
        $key = $document->fresh()->emission->operation_key;
        $edit = array_replace_recursive($case, ['id'=>$document->id,'series'=>$document->series,'number'=>$document->number,
            'invoice'=>['operation_type_id'=>'0202'], 'items'=>[0=>['quantity'=>3]]]); $edit['payments']=[];
        (new \App\CoreFacturalo\Facturalo)->forWebDocuments()->update($edit,$document->id);
        $fresh = $document->fresh();
        self::assertNotSame($key,$fresh->emission->operation_key);
        self::assertSame('not_requested',$fresh->emission->status);
        self::assertEquals($payments,$fresh->payments->toArray());
        self::assertEquals(300,$fresh->total_exportation);
        self::assertSame('CIF',$fresh->fiscal_data->catalog_snapshot['operation_type']['incoterm']);
        $company->fiscal_credentials=$credentials; $company->save();
        $before=$transmissions; app(\App\Services\Fiscal\HkaEmission::class)->send($fresh);
        self::assertSame($before+1,$transmissions);
        self::assertSame('confirmed',$fresh->fresh()->emission->status);

        // Race the actual web export edit against sending, using two isolated PHP processes.
        $company->fiscal_credentials = null; $company->save();
        $case['external_id'] = (string) \Illuminate\Support\Str::uuid();
        $race = (new \App\CoreFacturalo\Facturalo)->forWebDocuments()->save($case)->getDocument()->fresh();
        (new \App\Services\Fiscal\HkaEmissionPreparation)->prepare($race);
        $company->fiscal_credentials = $credentials; $company->save();
        $raceEdit = array_replace_recursive($case, ['id'=>$race->id, 'series'=>$race->series, 'number'=>$race->number,
            'invoice'=>['operation_type_id'=>'0202'], 'items'=>[0=>['quantity'=>3]], 'payments'=>[]]);
        $raceEdit['payments'] = [];
        $payments = $db->table('document_payments')->where('document_id',$race->id)->get()->toArray();
        $stockBefore = (float) $db->table('item_warehouse')->where('item_id',$case['items'][0]['item_id'])->value('stock');
        $documentsBefore = $db->table('documents')->count();
        $log = tempnam(sys_get_temp_dir(),'sab_export_edit_race_');
        try {
            $results = $this->runFiscalWorkers($race->id,'hka-edit-race',[
                ['action'=>'edit','edit'=>$raceEdit,'web_export'=>true], ['action'=>'send','log'=>$log]]);
            $after = $race->fresh();
            self::assertTrue(($results[0]['status'] ?? null)==='edited' || isset($results[0]['rejected']));
            self::assertContains($after->emission->status,['confirmed','not_requested']);
            self::assertEquals($payments,$db->table('document_payments')->where('document_id',$race->id)->get()->toArray());
            self::assertSame($documentsBefore,$db->table('documents')->count());
            self::assertEquals($stockBefore-($after->items->sum('quantity')-2),$db->table('item_warehouse')->where('item_id',$case['items'][0]['item_id'])->value('stock'));
            self::assertEquals($after->items->sum('total_value'),$after->total_exportation);
            self::assertSame($after->emission->status==='confirmed' ? "emission\n" : '',file_get_contents($log));
            if ($after->emission->status==='not_requested') app(\App\Services\Fiscal\HkaEmission::class)->send($after);
            $after = $after->fresh();
            self::assertSame('confirmed',$after->emission->status);
            $header = $after->emission->payload['documentoElectronico']['encabezado'];
            self::assertSame(\App\Services\ExportInvoicePolicy::INCOTERMS[$after->invoice->operation_type_id],$header['identificacionDocumento']['tipoDeVenta']);
            // ######## INICIO CALCULOS EXACTOS SAB ########
            self::assertTrue(\App\Support\Exact\Decimal::of($header['totales']['totalAPagar'])->isEqualTo($after->exactValue('total')));
            // ######## FIN CALCULOS EXACTOS SAB ########
            self::assertEquals($payments,$db->table('document_payments')->where('document_id',$race->id)->get()->toArray());
        } finally { unlink($log); }
    }

    public function test_hka_legal_identity_is_saved_and_missing_pending_mapping_recovers_from_frozen_customer(): void
    {
        [$db,$app,$input] = $this->bootHkaFixture();
        $sent=[];$mode='success';$this->fakeSpeHka($db,$app,$sent,$mode);
        $customer=\App\Models\Tenant\Person::findOrFail($input['customer_id']);
        $customer->forceFill(['identity_document_type_id'=>'6','number'=>'123456789'])->save();
        $input['customer']=$customer->toArray();
        $created=(new \App\CoreFacturalo\Facturalo)->save($input)->getDocument()->fresh();
        self::assertSame('J',$created->customer->hka_identity_code);
        self::assertSame('confirmed',$created->emission->status);
        self::assertSame('J',$created->emission->payload['documentoElectronico']['encabezado']['comprador']['tipoIdentificacion']);
        self::assertCount(1,$sent);

        $db->table('configurations')->update(['auto_send_hka'=>false]);
        $input['external_id']=(string)\Illuminate\Support\Str::uuid();
        $pending=(new \App\CoreFacturalo\Facturalo)->save($input)->getDocument()->fresh();
        $frozen=(array)$pending->customer;unset($frozen['hka_identity_code']);
        $pending->forceFill(['customer'=>$frozen])->save();
        $pending->emission()->update(['response'=>['auto_send_requested'=>false,'diagnostic'=>'Falta una equivalencia de identidad HKA.']]);
        $pending=$pending->fresh();$operation=$pending->emission->operation_key;
        $before=$db->table('documents')->where('id',$pending->id)->first();
        $payments=$db->table('document_payments')->get()->toArray();
        $stock=$db->table('item_warehouse')->get()->toArray();
        $customer->forceFill(['identity_document_type_id'=>'1','number'=>'87654321','name'=>'Cliente modificado después'])->save();
        $service=new \App\Services\Fiscal\HkaEmissionPreparation;
        $prepared=$service->prepare($pending);
        $buyer=$prepared->payload['documentoElectronico']['encabezado']['comprador'];
        self::assertSame('prepared',$prepared->status);
        self::assertSame($operation,$prepared->operation_key);
        self::assertSame('J',$buyer['tipoIdentificacion']);
        self::assertSame('123456789',$buyer['numeroIdentificacion']);
        self::assertNotSame($customer->name,$buyer['razonSocial']);
        self::assertSame(false,$prepared->response['auto_send_requested']);
        self::assertStringNotContainsString('Falta una equivalencia',$prepared->response['diagnostic']);
        self::assertEquals($before,$db->table('documents')->where('id',$pending->id)->first());
        self::assertEquals($payments,$db->table('document_payments')->get()->toArray());
        self::assertEquals($stock,$db->table('item_warehouse')->get()->toArray());
        self::assertCount(1,$sent,'Preparing a pending invoice must not transmit it.');
        self::assertSame($prepared->payload,$service->prepare($pending)->payload);
        foreach (['uncertain','confirmed'] as $status) {
            $pending->emission()->update(['status'=>$status]);
            try { $service->prepare($pending);self::fail('An in-flight or confirmed operation cannot be rebuilt.'); }
            catch (\Illuminate\Validation\ValidationException $expected) { self::assertArrayHasKey('emission',$expected->errors()); }
            self::assertSame($prepared->payload,$pending->fresh()->emission->payload);
        }
    }

    public function test_postgresql_list_balance_scope_keeps_fractions_and_releases_its_values(): void
    {
        [$db,$app,$input]=$this->bootHkaFixture();$input['payment_condition_id']='02';$input['payments']=[];
        $db->table('configurations')->update(['auto_send_hka'=>false]);
        $document=(new \App\CoreFacturalo\Facturalo())->save($input)->getDocument()->fresh();
        $db->table('document_payments')->insert(['document_id'=>$document->id,'date_of_payment'=>'2026-09-10','payment_method_type_id'=>'01','currency_type_id'=>'VES','exchange_rate'=>'1','operation_key'=>(string)\Illuminate\Support\Str::uuid(),'payment_method_snapshot'=>'{"collection_kind":"cash","currency_type_id":"VES"}','payment'=>'0.333333333333','original_amount'=>'0.333333333333','calculation_snapshot'=>json_encode(\App\Support\Exact\Decimal::snapshot(['payment'=>'1/3']))]);
        \App\Support\Exact\ReadBalanceScope::run(new \Illuminate\Database\Eloquent\Collection([$document]),function () use ($db,$document) {
            self::assertTrue($document->exactBalance()->isEqualTo('695/3'));
            $db->table('document_payments')->insert(['document_id'=>$document->id,'date_of_payment'=>'2026-09-10','payment_method_type_id'=>'01','currency_type_id'=>'VES','exchange_rate'=>'1','operation_key'=>(string)\Illuminate\Support\Str::uuid(),'payment_method_snapshot'=>'{"collection_kind":"cash","currency_type_id":"VES"}','payment'=>'0.1','original_amount'=>'0.1','calculation_snapshot'=>json_encode(\App\Support\Exact\Decimal::snapshot(['payment'=>'1/10']))]);
            self::assertTrue($document->exactBalance()->isEqualTo('695/3'));
            self::assertTrue($document->exactBalance(true)->isEqualTo('6947/30'));
        });
        self::assertTrue($document->exactBalance()->isEqualTo('6947/30'));
        try {\App\Support\Exact\ReadBalanceScope::run(new \Illuminate\Database\Eloquent\Collection([$document]),fn()=>throw new \RuntimeException('Injected render failure'));}
        catch (\RuntimeException $error) {self::assertSame('Injected render failure',$error->getMessage());}
        self::assertNull(\App\Support\Exact\ReadBalanceScope::value($document));
    }

    private function bootGlobalInventory($app): void
    {
        self::$globalInventoryDispatchers ??= new \WeakMap();
        $dispatcher=\Illuminate\Database\Eloquent\Model::getEventDispatcher();
        if (!isset(self::$globalInventoryDispatchers[$dispatcher])) {
            (new \App\Providers\KardexServiceProvider($app))->boot();self::$globalInventoryDispatchers[$dispatcher]=true;
        }
    }

    public function test_hka_prepares_unit_price_from_frozen_fraction_on_postgresql(): void
    {
        [$db, $app, $input] = $this->bootHkaFixture();
        $db->table('configurations')->update(['auto_send_hka' => false]);
        $input['payment_condition_id'] = '01';
        $input['payments'][0]['payment'] = '1.43';
        $input['items'][0]['quantity'] = '1';
        $input['items'][0]['unit_price'] = '1.431962';
        $decimal = \App\Support\Exact\Decimal::class;
        $preparer = new \App\Services\Fiscal\HkaEmissionPreparation();
        foreach (['1.2344499999996' => '1.2344', '1.23445' => '1.2345'] as $source => $expected) {
            $input['external_id'] = (string) \Illuminate\Support\Str::uuid();
            $input['items'][0]['price_input'] = [
                'amount' => $decimal::fraction($source), 'currency_type_id' => 'VES', 'includes_iva' => false,
            ];
            $document = $db->transaction(static fn () => (new \App\CoreFacturalo\Facturalo())->forWebDocuments()->save($input)->getDocument());
            $line = $document->items()->firstOrFail();
            self::assertSame('1.234450000000', $decimal::represent($line->getRawOriginal('unit_value'), 12, true));
            self::assertTrue($decimal::readRequired($line, 'unit_value')->isEqualTo($source));
            $prepared = $preparer->prepare($document);
            self::assertSame('prepared', $prepared->status);
            self::assertSame($expected, $prepared->payload['documentoElectronico']['detallesItems'][0]['precioUnitario']);
            self::assertSame($prepared->payload, $preparer->prepare($document->fresh())->payload);
        }
    }

    public function test_hka_prepares_line_adjustments_from_frozen_fractions_on_postgresql(): void
    {
        [$db, $app, $input] = $this->bootHkaFixture();
        $db->table('configurations')->update(['auto_send_hka' => false]);
        $input['payment_condition_id'] = '01';
        $input['payments'][0]['payment'] = '10';
        $input['items'][0] = array_replace($input['items'][0], [
            'quantity' => '1', 'unit_price' => '10', 'affectation_igv_type_id' => '20',
        ]);
        $decimal = \App\Support\Exact\Decimal::class;
        $preparer = new \App\Services\Fiscal\HkaEmissionPreparation();
        foreach (['0.0049999999996' => '0.00', '0.005' => '0.01'] as $source => $expected) {
            $input['external_id'] = (string) \Illuminate\Support\Str::uuid();
            $input['items'][0]['discounts'] = [[
                'discount_type_id' => '00', 'is_amount' => true, 'amount' => '0.005', 'amount_exact' => $decimal::fraction($source),
            ]];
            $input['items'][0]['charges'] = [[
                'charge_type_id' => '50', 'factor' => $decimal::fraction($decimal::of($source)->dividedBy(10)),
            ]];
            $document = $db->transaction(static fn () => (new \App\CoreFacturalo\Facturalo())->forWebDocuments()->save($input)->getDocument());
            $line = $document->items()->firstOrFail();
            foreach (['total_discount', 'total_charge'] as $field) {
                self::assertSame('0.005000000000', $decimal::represent($line->getRawOriginal($field), 12, true));
                self::assertTrue($decimal::readRequired($line, $field)->isEqualTo($source));
                self::assertTrue($decimal::readRequired($document, $field)->isEqualTo($source));
            }
            $prepared = $preparer->prepare($document);
            $payload = $prepared->payload['documentoElectronico'];
            self::assertSame($expected, $payload['detallesItems'][0]['descuentoMonto']);
            self::assertSame($expected, $payload['detallesItems'][0]['recargoMonto']);
            self::assertSame($expected, $payload['encabezado']['totales']['totalDescuento']);
            self::assertSame($expected, $payload['encabezado']['totales']['totalRecargos']);
            self::assertSame('10.00', $payload['encabezado']['totales']['totalAPagar']);
            self::assertSame($prepared->payload, $preparer->prepare($document->fresh())->payload);
        }
    }

    public function test_hka_reconciles_cents_across_ordinary_ves_invoice_lines_on_postgresql(): void
    {
        [$db, $app, $input] = $this->bootHkaFixture();
        $db->table('configurations')->update(['auto_send_hka' => false]);
        $input['payment_condition_id'] = '01';
        $input['payments'][0]['payment'] = '0.01';
        $line = array_replace($input['items'][0], [
            'quantity' => '1', 'unit_price' => '0.005', 'affectation_igv_type_id' => '20',
        ]);
        $input['items'] = [$line, $line];
        $document = (new \App\CoreFacturalo\Facturalo())->forWebDocuments()->save($input)->getDocument()->fresh();
        self::assertSame('1/100', (string) $document->exactValue('total'));
        $lines = $document->items()->orderBy('id')->get();
        self::assertSame(['1/200', '1/200'], $lines->map(fn ($line) => (string) $line->exactValue('total'))->all());

        $preparer = new \App\Services\Fiscal\HkaEmissionPreparation();
        $prepared = $preparer->prepare($document);
        $payload = $prepared->payload['documentoElectronico'];
        self::assertSame(['0.01', '0.00'], array_column($payload['detallesItems'], 'valorTotalItem'));
        self::assertSame(['0.00', '0.00'], array_column($payload['detallesItems'], 'descuentoMonto'));
        self::assertSame('0.01', $payload['encabezado']['totales']['totalAPagar']);
        self::assertSame('0.01', $payload['encabezado']['totales']['formasPago'][0]['monto']);
        self::assertSame($prepared->payload, $preparer->prepare($document->fresh())->payload);
        self::assertSame(['1/200', '1/200'], $document->items()->orderBy('id')->get()->map(fn ($line) => (string) $line->exactValue('total'))->all());
    }

    public function test_hka_prepares_exempt_base_from_frozen_fraction_on_postgresql(): void
    {
        [$db, $app, $input] = $this->bootHkaFixture();
        $db->table('configurations')->update(['auto_send_hka' => false]);
        $input['payment_condition_id'] = '01';
        $input['items'][0] = array_replace($input['items'][0], [
            'quantity' => '1', 'unit_price' => '10.005', 'affectation_igv_type_id' => '20',
        ]);
        $decimal = \App\Support\Exact\Decimal::class;
        $preparer = new \App\Services\Fiscal\HkaEmissionPreparation();
        foreach (['10.0049999999996' => '10.00', '10.005' => '10.01'] as $source => $expected) {
            $input['external_id'] = (string) \Illuminate\Support\Str::uuid();
            $input['payments'][0]['payment'] = $expected;
            $input['items'][0]['price_input'] = [
                'amount' => $decimal::fraction($source), 'currency_type_id' => 'VES', 'includes_iva' => true,
            ];
            $document = $db->transaction(static fn () => (new \App\CoreFacturalo\Facturalo())->forWebDocuments()->save($input)->getDocument())->fresh();
            self::assertSame('10.005000000000', $decimal::represent($document->getRawOriginal('total_exonerated'), 12, true));
            self::assertTrue($decimal::readRequired($document, 'total_exonerated')->isEqualTo($source));
            $totals = $preparer->prepare($document)->payload['documentoElectronico']['encabezado']['totales'];
            self::assertSame($expected, $totals['montoExentoTotal']);
            self::assertSame($expected, $totals['subtotal']);
            self::assertSame($expected, $totals['totalAPagar']);
        }
    }

    /** @dataProvider retentionForecastCapabilities */
    public function test_hka_blocks_a_retention_forecast_using_the_exact_postgresql_shortfall(string $tax, string $affectation, string $capability): void
    {
        [$db, , $input, $state] = $this->bootIslrFixture();
        $db->table('configurations')->update(['auto_send_hka' => false]);
        config(['iva_retention.demo_net_collection_verified' => false, 'islr_retention.demo_net_collection_verified' => false]);
        $field = strtolower($tax).'_retention';
        unset($input[($tax === 'IVA' ? 'islr' : 'iva').'_retention_prevision']);
        if ($tax === 'ISLR') $input['islr_retention_prevision']['subtrahend'] = '0';
        $input['items'][0] = array_replace($input['items'][0], [
            'quantity' => '0.1', 'unit_price' => '10.050000000001', 'affectation_igv_type_id' => $affectation,
        ]);
        $input['payments'][0]['payment'] = '1';

        $document = (new \App\CoreFacturalo\Facturalo())->save($input)->getDocument()->fresh();
        self::assertTrue($document->fiscal_data->{$field.'_prevision'}['eligible']);
        self::assertSame('1.005000000000', $document->getRawOriginal('total'));
        self::assertTrue($document->exactValue('total')->isEqualTo('1.0050000000001'));
        self::assertTrue($document->payments->first()->exactValue('payment')->isEqualTo('1'));
        self::assertSame($capability::BLOCK_REASON, $capability::reason($document));

        try {
            (new \App\Services\Fiscal\HkaEmissionPreparation())->prepare($document);
            self::fail('La previsión con saldo exacto fuera de tolerancia debe bloquear la preparación.');
        } catch (\Illuminate\Validation\ValidationException $error) {
            self::assertSame([$capability::BLOCK_REASON], $error->errors()[$field] ?? []);
        }
        $result = (new \App\Services\Fiscal\HkaEmission())->send($document);
        self::assertSame('not_requested', $result['status']);
        self::assertSame($capability::BLOCK_REASON, $result['diagnostic']);
        self::assertNull($document->fresh()->emission->payload);
        self::assertSame([], $state->calls);
    }

    public static function retentionForecastCapabilities(): array
    {
        return [
            'IVA' => ['IVA', '10', \App\Services\Fiscal\HkaIvaRetentionCapability::class],
            'ISLR' => ['ISLR', '20', \App\Services\Fiscal\HkaIslrRetentionCapability::class],
        ];
    }

    public function test_invoice_accepts_a_positive_iva_forecast_below_column_precision(): void
    {
        [$db, , $input, $state] = $this->bootIvaFixture();
        $db->table('companies')->update(['fiscal_emission_mode' => 'free_form']);
        $db->table('configurations')->update(['auto_send_hka' => false]);
        $input['payment_condition_id'] = '02';
        $input['payments'] = [];
        $input['items'][1] = array_replace($input['items'][0], ['affectation_igv_type_id' => '20', 'quantity' => '1', 'unit_price' => '10']);
        $input['items'][0] = array_replace($input['items'][0], ['quantity' => '0.000000000002', 'unit_price' => '1.16']);

        $document = (new \App\CoreFacturalo\Facturalo())->save($input)->getDocument()->fresh();
        self::assertSame('0.000000000000', $document->getRawOriginal('total_igv'));
        self::assertTrue($document->exactValue('total_igv')->isEqualTo('0.00000000000032'));
        $forecast = $document->fiscal_data->iva_retention_prevision;
        self::assertTrue($forecast['eligible']);
        self::assertTrue(\App\Support\Exact\Decimal::readRequired($forecast, 'amount')->isEqualTo('0.00000000000024'));
        self::assertCount(0, $document->received_retentions);
        self::assertSame([], $state->calls);

        $voucher = $this->ivaVoucher($document, ['base' => '0.01', 'amount' => '0.01']);
        try {
            (new \ReflectionMethod(\App\Services\Fiscal\IvaRetentionService::class, 'validatePending'))->invoke(null, $document, $voucher);
            self::fail('El IVA sin importe liquidable no admite aplicar un centavo como comprobante.');
        } catch (\Illuminate\Validation\ValidationException $error) {
            self::assertArrayHasKey('document', $error->errors());
        }
        self::assertSame(0, $document->received_retentions()->count());

        $input['external_id'] = (string) \Illuminate\Support\Str::uuid();
        $input['items'][0]['affectation_igv_type_id'] = '20';
        try {
            (new \App\CoreFacturalo\Facturalo())->save($input);
            self::fail('Una factura con IVA exactamente cero no admite una previsión.');
        } catch (\Illuminate\Validation\ValidationException $error) {
            self::assertArrayHasKey('document', $error->errors());
        }
        self::assertSame(1, $db->table('documents')->count());
    }

    public function test_invoice_edit_keeps_positive_iva_forecast_until_the_exact_tax_is_zero(): void
    {
        [$db, , $input, $state] = $this->bootIvaFixture();
        $db->table('companies')->update(['fiscal_emission_mode' => 'free_form']);
        $db->table('configurations')->update(['auto_send_hka' => false]);
        $input['payment_condition_id'] = '02';
        $input['payments'] = [];
        $input['items'][1] = array_replace($input['items'][0], ['affectation_igv_type_id' => '20', 'quantity' => '1', 'unit_price' => '10']);
        $document = (new \App\CoreFacturalo\Facturalo())->save($input)->getDocument()->fresh();
        $original = $document->fiscal_data->iva_retention_prevision;

        unset($input['iva_retention_prevision']);
        $edit = array_replace($input, ['id' => $document->id, 'series' => $document->series, 'number' => $document->number,
            'received_retentions' => [], 'guarantee_fund' => null]);
        foreach ($document->items()->orderBy('id')->pluck('id') as $index => $lineId) $edit['items'][$index]['record_id'] = $lineId;
        $edit['items'][0]['quantity'] = '0.000000000002';
        $edit['items'][0]['unit_price'] = '1.16';
        (new \App\CoreFacturalo\Facturalo())->update($edit, $document->id);

        $current = $document->fresh();
        self::assertSame('0.000000000000', $current->getRawOriginal('total_igv'));
        self::assertTrue($current->exactValue('total_igv')->isEqualTo('0.00000000000032'));
        $forecast = $current->fiscal_data->iva_retention_prevision;
        self::assertNotNull($forecast);
        self::assertSame($original['saved_at'], $forecast['saved_at']);
        self::assertTrue(\App\Support\Exact\Decimal::readRequired($forecast, 'amount')->isEqualTo('0.00000000000024'));

        foreach ($current->items()->orderBy('id')->pluck('id') as $index => $lineId) $edit['items'][$index]['record_id'] = $lineId;
        $edit['items'][0]['affectation_igv_type_id'] = '20';
        (new \App\CoreFacturalo\Facturalo())->update($edit, $document->id);
        self::assertTrue($document->fresh()->exactValue('total_igv')->isZero());
        self::assertNull($document->fresh()->fiscal_data->iva_retention_prevision);
        self::assertSame([], $state->calls);
    }

    public function test_hka_rejects_export_fraction_in_an_internal_invoice_snapshot_on_postgresql(): void
    {
        [$db, $app, $input] = $this->bootHkaFixture();
        $db->table('configurations')->update(['auto_send_hka' => false]);
        $document = $db->transaction(static fn () => (new \App\CoreFacturalo\Facturalo())->forWebDocuments()->save($input)->getDocument());
        $decimal = \App\Support\Exact\Decimal::class;
        $source = '0.0000000000004';
        $snapshot = $document->calculation_snapshot;
        $snapshot['values']['total_exportation'] = $decimal::fraction($source);
        $db->table('documents')->where('id', $document->id)->update([
            'total_exportation' => $decimal::represent($source), 'calculation_snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR),
        ]);
        $document->refresh();
        self::assertTrue($decimal::of($document->getRawOriginal('total_exportation'))->isZero());
        self::assertTrue($decimal::readRequired($document, 'total_exportation')->isPositive());
        try {
            (new \App\Services\Fiscal\HkaEmissionPreparation())->prepare($document);
            self::fail('Una operación interna no puede declarar un valor de exportación.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            self::assertArrayHasKey('fiscal_data', $e->errors());
        }
        self::assertSame('not_requested', $document->emission()->firstOrFail()->status);
    }

    public function test_hka_groups_igtf_from_two_converted_receipts_before_rounding_on_postgresql(): void
    {
        [$db, $app, $input] = $this->bootHkaFixture();
        $db->table('configurations')->update(['auto_send_hka' => false]);
        $db->table('companies')->update(['igtf_enabled' => true, 'igtf_rate' => '3']);
        \Tests\Support\SpeProfiles::versions($db, 1, $input['customer_id']);
        $input['payment_condition_id'] = '01';
        $input['items'][0] = array_replace($input['items'][0], [
            'quantity' => '1', 'unit_price' => '7.08', 'affectation_igv_type_id' => '20',
        ]);
        $receipt = array_replace($input['payments'][0], [
            'currency_type_id' => 'USD', 'payment_method_type_id' => '10', 'original_amount' => '0.34',
            'financial_intermediation' => 'none', 'exchange_rate' => '10.4',
        ]);
        $input['payments'] = array_map(static fn () => array_replace($receipt, [
            'operation_key' => (string) \Illuminate\Support\Str::uuid(),
        ]), [1, 2]);
        $document = $db->transaction(static fn () => (new \App\CoreFacturalo\Facturalo())->forWebDocuments()->save($input)->getDocument())->fresh();
        $taxes = $document->taxes()->where('tax_kind', 'IGTF')->get();
        self::assertCount(2, $taxes);
        foreach ($taxes as $tax) {
            self::assertTrue($tax->exactValue('base')->isEqualTo('3.536'));
            self::assertTrue($tax->exactValue('amount')->isEqualTo('0.104'));
        }
        $prepared = (new \App\Services\Fiscal\HkaEmissionPreparation())->prepare($document);
        $totals = $prepared->payload['documentoElectronico']['encabezado']['totales'];
        $tax = collect($totals['impuestosSubtotal'])->firstWhere('codigoTotalImp', 'IGTF');
        self::assertSame('0.21', $totals['totalIGTF']);
        self::assertSame($totals['totalIGTF'], $tax['valorTotalImp']);
        self::assertSame('7.07', $tax['baseImponibleImp']);
        self::assertSame($prepared->payload, (new \App\Services\Fiscal\HkaEmissionPreparation())->prepare($document->fresh())->payload);
    }

    private function bootHkaFixture(bool $openCash = true): array
    {
        $db = $this->capsule->getConnection('tenant');
        $this->prepareFiscalFixtureSchema();
        $app = Container::getInstance();
        $db = $this->capsule->getConnection('tenant');
        $app->instance('files', new \Illuminate\Filesystem\Filesystem());
        $db->table('companies')->insert(['id' => 1, 'identity_document_type_id' => '6', 'number' => 'J123456789', 'name' => 'Test fiscal', 'trade_name' => 'Test fiscal', 'fiscal_environment' => 'demo', 'fiscal_emission_mode' => 'digital']);
        $db->table('configurations')->insert(['id' => 1, 'quantity_documents' => 0, 'quantity_sales_notes' => 0]);
        $establishment = $db->table('establishments')->insertGetId(['description' => 'Test fiscal', 'country_id' => 'VE', 'department_id' => '14', 'province_id' => '0229', 'district_id' => '000619', 'address' => 'Test', 'telephone' => '04121234567', 'code' => '0000']);
        $warehouse = $db->table('warehouses')->insertGetId(['establishment_id' => $establishment, 'description' => 'Test warehouse']);
        $userId = $db->table('users')->insertGetId(['name' => 'Test operator', 'email' => 'fiscal@example.test', 'password' => 'not-a-login-hash', 'type' => 'admin', 'establishment_id' => $establishment]);
        $user = \App\Models\Tenant\User::findOrFail($userId);
        $app->instance('auth', new class($user) {
            private $user;
            public function __construct($user) { $this->user = $user; }
            public function user() { return $this->user; }
            public function setUser($user) { $this->user = $user; return $this; }
            public function id() { return $this->user->id; }
            public function check() { return true; }
            public function guard($name = null) { return $this; }
        });
        $item = \App\Models\Tenant\Item::where('internal_id', 'MOCK-ITEM-VES-001')->firstOrFail();
        $db->table('item_warehouse')->insert(['item_id' => $item->id, 'warehouse_id' => $warehouse, 'stock' => 10]);
        $customer = \App\Models\Tenant\Person::where('number', '12345678')->where('identity_document_type_id', '1')->firstOrFail();
        foreach (['01' => 'FF01', '07' => 'FC01', '08' => 'FD01', '09' => 'TT01', '80' => 'NV01', 'U2' => 'AI01', 'U3' => 'AS01', 'U4' => 'AT01'] as $type => $code) {
            $seriesId = $db->table('series')->insertGetId(['establishment_id' => $establishment, 'document_type_id' => $type, 'number' => $code]);
            $db->table('series_configurations')->insert(['series_id' => $seriesId, 'document_type_id' => $type, 'series' => $code, 'number' => 100]);
        }
        $input = [
            'type' => 'invoice', 'user_id' => $userId, 'external_id' => \Illuminate\Support\Str::uuid()->toString(),
            'establishment_id' => $establishment, 'establishment' => (array) $db->table('establishments')->find($establishment),
            'state_type_id' => '01', 'group_id' => '01', 'document_type_id' => '01',
            'series' => 'FF01', 'number' => '#', 'date_of_issue' => '2026-09-10', 'time_of_issue' => '12:00:00',
            'customer_id' => $customer->id, 'customer' => $customer->toArray(), 'currency_type_id' => 'VES',
            'exchange_rate_sale' => 1, 'total_taxed' => 200, 'total_igv' => 32, 'total_taxes' => 32, 'total_value' => 200, 'total' => 232, 'additional_information' => '',
            'payments' => [['date_of_payment' => '2026-09-10', 'payment_method_type_id' => '01', 'payment' => 232, 'reference' => 'Test payment']],
            'fee' => [], 'hotel' => null, 'transport' => null, 'invoice' => ['date_of_due' => '2026-09-10', 'operation_type_id' => '0101'],
            'items' => [[
                'item_id' => $item->id, 'item' => array_replace($item->toArray(), ['is_set' => false]), 'quantity' => 2, 'warehouse_id' => $warehouse,
                'unit_value' => 100, 'unit_price' => 116, 'price_type_id' => '01', 'affectation_igv_type_id' => '10', 'additional_information' => '',
                'total_base_igv' => 200, 'percentage_igv' => 16, 'total_igv' => 32, 'total_taxes' => 32, 'total_value' => 200, 'total' => 232,
            ]],
        ];
        \Illuminate\Database\Eloquent\Model::clearBootedModels();
        \Illuminate\Database\Eloquent\Model::setEventDispatcher(new \Illuminate\Events\Dispatcher($app));
        \App\Models\Tenant\Document::observe(\App\Observers\DocumentObserver::class);
        $this->bootGlobalInventory($app);
        (new \Modules\Inventory\Providers\InventoryKardexServiceProvider($app))->boot();
        (new \Modules\Inventory\Providers\InventoryVoidedServiceProvider($app))->boot();
        $app->instance('encrypter', new \Illuminate\Encryption\Encrypter(str_repeat('k', 32), 'AES-256-CBC'));
        config(['app.key' => str_repeat('k', 32)]);
        $company = \App\Models\Tenant\Company::firstOrFail();
        $company->fiscal_credentials = json_encode(['usuario' => 'test-user', 'clave' => 'test-secret']);
        $company->save();
        $http = new \Illuminate\Http\Client\Factory();
        $app->instance(\Illuminate\Http\Client\Factory::class, $http);
        $customer->forceFill(['email' => 'receiver@example.test', 'telephone' => '04121234567'])->save();
        $input['customer'] = $customer->toArray();
        $input['payment_condition_id'] = '01';
        if ($openCash) {
            $cash=\App\Models\Tenant\Cash::create(['user_id'=>$userId,'state'=>true,'date_opening'=>'2026-09-10','time_opening'=>'12:00:00','beginning_balance'=>'0']);
            $db->transaction(fn()=>\App\Services\Payments\CashLedger::open($cash,[]));
        }
        return [$db, $app, $input];
    }

    /** @dataProvider fiscalChannelModes */
    public function test_exact_calculations_survive_postgresql_reads_grouping_discounts_and_rollback(string $fiscalMode): void
    {
        [$db, $app, $input] = $this->bootHkaFixture();
        $db->table('companies')->update(['fiscal_emission_mode'=>$fiscalMode]);
        $db->table('configurations')->update(['auto_send_hka'=>false]);
        $db->table('item_warehouse')->update(['stock'=>'1000']);
        $input['payments'] = []; $input['payment_condition_id'] = '02';
        $input['items'][0]['quantity'] = '1';
        $input['items'][0]['unit_price'] = '500';
        $document = (new \App\CoreFacturalo\Facturalo())->save($input)->getDocument()->fresh();
        $D = \App\Support\Exact\Decimal::class;
        self::assertSame($fiscalMode, $document->fiscal_emission_mode);
        self::assertSame('500', $D::represent($document->exactValue('total')));
        self::assertSame('500', $D::represent($document->exactValue('total_payable')));
        $line = $document->items()->firstOrFail();
        self::assertSame('500', $D::represent($line->exactValue('unit_value')->multipliedBy('1.16')));
        self::assertSame('431.034482758621', $D::represent($line->exactValue('total_value')));
        self::assertSame(1, $line->calculation_snapshot['version']);
        self::assertSame('500', $document->balance);

        $feeAmounts=$D::allocate('500',[1,1,1]);
        $withFee=array_replace($input,['external_id'=>(string)\Illuminate\Support\Str::uuid(),
            'payment_condition_id'=>'02','fee'=>array_map(fn($amount)=>['date'=>'2026-11-16','currency_type_id'=>'VES',
                'amount'=>$amount,'allocation_weight'=>'1'],$feeAmounts)]);
        $scheduled=(new \App\CoreFacturalo\Facturalo())->save($withFee)->getDocument()->fresh()->fee;
        self::assertSame('500',$D::represent($D::sum($scheduled->map(fn($fee)=>$fee->exactValue('amount')))));
        foreach ($scheduled as $fee) self::assertSame(['n'=>'500','d'=>'3'],$D::fraction($fee->exactValue('scheduled_exact')));

        $grouped = array_replace($input, ['external_id'=>(string)\Illuminate\Support\Str::uuid()]);
        $grouped['items'][0]['quantity'] = '100';
        $one = (new \App\CoreFacturalo\Facturalo())->save($grouped)->getDocument()->fresh();
        $grouped['external_id'] = (string)\Illuminate\Support\Str::uuid();
        $grouped['items'] = array_fill(0, 100, $input['items'][0]);
        $many = (new \App\CoreFacturalo\Facturalo())->save($grouped)->getDocument()->fresh();
        self::assertSame('50000', $D::represent($one->exactValue('total')));
        self::assertTrue($one->exactValue('total')->isEqualTo($many->exactValue('total')));

        $discounted = array_replace($input, ['external_id'=>(string)\Illuminate\Support\Str::uuid()]);
        $discounted['items'][0]['unit_price'] = '100';
        $discounted['items'][0]['affectation_igv_type_id'] = '20';
        $discounted['items'][0]['discounts'] = [['discount_type_id'=>'00','is_amount'=>true,
            'from_global_distribution'=>true,'amount'=>'0.333333333333','amount_exact'=>['n'=>'1','d'=>'3']]];
        $discounted['items'] = array_fill(0, 3, $discounted['items'][0]);
        $discounted = (new \App\CoreFacturalo\Facturalo())->save($discounted)->getDocument()->fresh();
        self::assertSame('299', $D::represent($discounted->exactValue('total')));
        self::assertSame('1', $D::represent($discounted->exactValue('total_discount')));

        $before = $db->table('documents')->count();
        $stock = $db->table('item_warehouse')->value('stock');
        $invalid = array_replace($input, ['external_id'=>(string)\Illuminate\Support\Str::uuid()]);
        $invalid['items'][0]['unit_price'] = '500.0000000000001';
        try { (new \App\CoreFacturalo\Facturalo())->save($invalid); self::fail('Excess input precision must be rejected.'); }
        catch (\Illuminate\Validation\ValidationException $expected) { self::assertNotEmpty($expected->errors()); }
        self::assertSame($before, $db->table('documents')->count());
        self::assertSame($stock, $db->table('item_warehouse')->value('stock'));

        // ######## INICIO CALCULOS EXACTOS SAB ########
        $mixed = array_replace($input, ['external_id'=>(string)\Illuminate\Support\Str::uuid(),
            'date_of_issue'=>'2026-10-19','total_prepayment'=>'10',
            'discounts'=>[['discount_type_id'=>'02','amount'=>'10']],
            'charges'=>[['charge_type_id'=>'50','amount'=>'6']]]);
        $mixed['items'][0]['unit_price'] = '116';
        $mixed['items'][0]['quantity'] = '1';
        $mixed['items'][] = array_replace($mixed['items'][0], ['unit_price'=>'100','affectation_igv_type_id'=>'20']);
        $mixedDocument = (new \App\CoreFacturalo\Facturalo())->save($mixed)->getDocument()->fresh();
        self::assertSame('200.4',$D::represent($mixedDocument->exactValue('total')));
        $cash=\App\Models\Tenant\Cash::firstOrFail();
        \App\Models\Tenant\CashDocument::firstOrCreate(['cash_id'=>$cash->id,'document_id'=>$mixedDocument->id]);
        $productRows=(new \Modules\CashReport\Services\Builders\ProductsBuilder())->getDataReport($cash->id)['documents'];
        $mixedIds=$mixedDocument->items()->pluck('id')->all();
        $mixedRows=$productRows->filter(fn($row)=>$row['record_type']==='document_item' && in_array($row['id'],$mixedIds,true));
        self::assertCount(2,$mixedRows);
        self::assertTrue($D::sum($mixedRows->map(fn($row)=>$D::readRequired($row,'total')))->isEqualTo($mixedDocument->exactValue('total')));
        $exemptLine = $mixedDocument->items()->where('affectation_igv_type_id','20')->firstOrFail();
        self::assertTrue($D::readRequired($exemptLine,'header_discount')->isZero());
        self::assertSame('100',$D::represent($exemptLine->exactValue('total_base_igv')));
        $vesOnly = count($this->invoiceTestCurrencies()) === 1;
        $fractional = array_replace($input,['external_id'=>(string)\Illuminate\Support\Str::uuid(),
            'date_of_issue'=>'2026-10-19','currency_type_id'=>$vesOnly ? 'VES' : 'USD','exchange_rate_sale'=>'3.00000000']);
        $fractional['items'][0]['price_input'] = ['amount'=>$vesOnly ? ['n'=>'1','d'=>'3'] : '1','currency_type_id'=>'VES','includes_iva'=>true];
        $fractionalDocument = (new \App\CoreFacturalo\Facturalo())->save($fractional)->getDocument()->fresh();
        self::assertSame('1/3',(string)$fractionalDocument->exactValue('total'));
        $report = (new \Modules\Report\Http\Controllers\ReportSaleConsolidatedController())->detailRecords([
            'document_type_id'=>'[]','date_range_type_id'=>'date_of_issue','date_start'=>'2026-10-19','date_end'=>'2026-10-19','person_id'=>null]);
        $summary = \App\Support\Exact\SalesConsolidated::summary($report);
        self::assertSame($vesOnly ? '200.733333333333' : '201.4',$summary['total_sale']);
        self::assertSame('10',$summary['header_discount']);
        self::assertSame('10',$summary['header_prepayment']);
        self::assertSame('-1.6',$summary['header_iva_adjustment']);
        $receivables = collect(iterator_to_array(\App\Support\Exact\Receivables::dataset(['stablishmentUnpaidAll'=>1,
            'period'=>'date','date_start'=>'2026-10-19'])))->keyBy('report_key');
        self::assertTrue($D::readRequired($receivables['document:'.$mixedDocument->id],'total_to_pay')->isEqualTo($mixedDocument->exactBalance()));
        self::assertSame('-1/300',(string)$D::readRequired($receivables['document:'.$fractionalDocument->id],'settlement_adjustment'));
        $balanceSummary = \App\Support\Exact\OutstandingSummary::summarize($receivables);
        self::assertSame($vesOnly ? '200.73' : '201.39',$balanceSummary['consolidated_balance_ves']);
        self::assertSame($vesOnly ? '200.733333333333' : '201.4',$balanceSummary['consolidated_total_ves']);
        // A payment committed between the source and payment queries must not split a report's cut.
        $this->capsule->addConnection($db->getConfig(),'exact_report_writer');
        $writer = $this->capsule->getConnection('exact_report_writer');
        $interleaved = false;
        $db->listen(function ($query) use (&$interleaved,$writer,$mixedDocument) {
            if ($interleaved || $query->connectionName !== 'tenant' || !str_contains($query->sql,'from "documents"') || !str_contains($query->sql,'limit 500')) return;
            $interleaved = true;
            $writer->table('document_payments')->insert(['document_id'=>$mixedDocument->id,'date_of_payment'=>'2026-10-19',
                'payment_method_type_id'=>'01','payment'=>'33.4','currency_type_id'=>'VES','exchange_rate'=>'1.00000000',
                'original_amount'=>'33.4','payment_method_snapshot'=>json_encode(['id'=>'01','description'=>'Efectivo']),
                'operation_key'=>(string)\Illuminate\Support\Str::uuid(),
                'calculation_snapshot'=>json_encode(\App\Support\Exact\Decimal::snapshot(['payment'=>'33.4','original_amount'=>'33.4','tax_amount'=>'0']))]);
        });
        $stable = collect(iterator_to_array(\App\Support\Exact\Receivables::dataset(['stablishmentUnpaidAll'=>1,
            'period'=>'date','date_start'=>'2026-10-19'])))->keyBy('report_key');
        self::assertTrue($interleaved);
        self::assertSame('200.4',$stable['document:'.$mixedDocument->id]['total_to_pay']);
        self::assertSame('167',$mixedDocument->fresh()->balance);
        $widget = (new \Modules\Dashboard\Helpers\DashboardData())->debtors(['period'=>'date','date_start'=>'2026-10-19']);
        self::assertSame($vesOnly ? '167.33' : '167.99',$widget['total']);
        self::assertSame($widget['total'],$widget['summary']['consolidated_balance_ves']);
        $db->table('item_warehouse')->where('item_id',$input['items'][0]['item_id'])->update([
            'stock'=>'0.333333333333','calculation_snapshot'=>json_encode($D::snapshot(['stock'=>'1/3']))]);
        $stockRows = iterator_to_array(\App\Support\Exact\DashboardStock::dataset($input['establishment_id']));
        $stockRows = array_values(array_filter($stockRows,fn($row)=>$row['item_id'] === $input['items'][0]['item_id']));
        self::assertCount(1,$stockRows);
        self::assertSame('1/3',(string)$D::readRequired($stockRows[0],'stock'));
        $db->table('item_warehouse')->where('item_id',$input['items'][0]['item_id'])->update([
            'stock'=>'10','calculation_snapshot'=>json_encode($D::snapshot(['stock'=>'10']))]);
        $periodic = array_replace($input,['external_id'=>(string)\Illuminate\Support\Str::uuid()]);
        $periodic['items'][0]['affectation_igv_type_id']='20';$periodic['items'][0]['unit_price']='1';
        $advanceDocument=(new \App\CoreFacturalo\Facturalo())->save($periodic)->getDocument()->fresh();
        $advanceDocument->total_prepayment=$D::of('1/3');
        \App\Services\Fiscal\ExactDocumentSummary::save($advanceDocument);
        self::assertSame('1/3',(string)$advanceDocument->fresh()->exactValue('total_prepayment'));
        self::assertSame('2/3',(string)$advanceDocument->fresh()->exactValue('total'));
        self::assertSame('1/3',(string)$D::readRequired($advanceDocument->fresh()->items()->first(),'header_prepayment'));
        $finances='{"apply_arrears":true,"arrears_amount":"0.123456789012"}';
        $db->table('configurations')->update(['finances'=>$finances]);
        \App\Support\Exact\ReadSnapshot::run($db,function () use ($db,$writer,$finances,$D) {
            $rows=\App\Support\Exact\Receivables::dataset(['stablishmentUnpaidAll'=>1,'period'=>'date','date_start'=>'2026-10-19']);
            $writer->table('configurations')->update(['finances'=>'{"apply_arrears":true,"arrears_amount":"99"}']);
            self::assertEquals(json_decode($finances,true),json_decode($db->table('configurations')->value('finances'),true));
            foreach ($rows as $row) self::assertTrue($D::readRequired($row,'arrears')->isEqualTo($D::of('0.123456789012')->multipliedBy($row['delay_payment'])));
        });
        // ######## FIN CALCULOS EXACTOS SAB ########
    }

    public function test_exact_commercial_lifecycle_preserves_quantities_and_reverses_stock(): void
    {
        [$db,$app,$input]=$this->bootHkaFixture();
        $db->table('configurations')->update(['auto_send_hka'=>false]);
        $db->table('item_warehouse')->update(['stock'=>'1000']);
        $input['items'][0]['quantity']='1';
        $D=\App\Support\Exact\Decimal::class;
        // Commercial documents also recalculate their headers on the server.
        $saleNote = new \App\Models\Tenant\SaleNote();
        $saleNote->fill(array_intersect_key($input,array_flip($saleNote->getFillable())));
        $saleNote->forceFill(['prefix'=>'NV','document_type_id'=>'80','series'=>'NV01','number'=>'#','total'=>'0','external_id'=>(string)\Illuminate\Support\Str::uuid(),
            'user_id'=>auth()->id(),'state_type_id'=>'01'])->save();
        $commercialLine = array_replace($input['items'][0],['unit_price'=>'500','total'=>'499.99','price_type_id'=>'01']);
        $savedLine = $saleNote->items()->create($commercialLine);
        self::assertSame('500',$D::represent($savedLine->fresh()->exactValue('total')));
        self::assertSame('500',$D::represent($saleNote->fresh()->exactValue('total')));
        // The commercial edit flow replaces quantity lines and reverses their movement first.
        $savedLine->delete();
        $savedLine=$saleNote->items()->create(array_replace($commercialLine,['quantity'=>'3']));
        self::assertSame('1500',$D::represent($saleNote->fresh()->exactValue('total')));
        $savedLine->delete();
        self::assertSame('0',$D::represent($saleNote->fresh()->exactValue('total')));
        self::assertSame('1000',$D::represent(\Modules\Inventory\Models\ItemWarehouse::firstOrFail()->exactValue('stock')));
        $commercialLine=array_replace($commercialLine,['unit_price'=>'3','quantity'=>'166.666666666667',
            'quantity_input'=>['amount'=>'500','unit_price'=>'3']]);
        $periodic=$saleNote->items()->create($commercialLine);
        self::assertSame('500',$D::represent($saleNote->fresh()->exactValue('total')));
        $periodic=$periodic->fresh();$periodic->unit_price='6';$periodic->save();
        self::assertSame('1000',$D::represent($saleNote->fresh()->exactValue('total')));
        self::assertSame('2500',$D::represent(\Modules\Inventory\Models\ItemWarehouse::firstOrFail()->exactValue('stock')->multipliedBy(3)));
        // ######## INICIO CALCULOS EXACTOS SAB ########
        $report = (new \Modules\Report\Http\Controllers\ReportSaleConsolidatedController())->detailRecords([
            'document_type_id'=>'[]', 'date_range_type_id'=>'date_of_issue', 'date_start'=>'2026-09-01', 'date_end'=>'2026-09-30', 'person_id'=>null,
        ]);
        self::assertCount(1, $report);
        $reportTotals = \App\Support\Exact\SalesConsolidated::summary($report);
        self::assertSame('1000', $reportTotals['total_sale']);
        self::assertTrue($D::readRequired($reportTotals, 'quantity')->isEqualTo($D::of(500)->dividedBy(3)));
        // ######## FIN CALCULOS EXACTOS SAB ########
        $periodic->delete();
        self::assertSame('1000',$D::represent(\Modules\Inventory\Models\ItemWarehouse::firstOrFail()->exactValue('stock')));
    }

    // ######## INICIO CALCULOS EXACTOS SAB ########
    public function test_exact_stock_import_retains_periodic_delta_and_repeating_the_file_does_not_move_stock(): void
    {
        [$db, $app, $input] = $this->bootHkaFixture();
        $D = \App\Support\Exact\Decimal::class;
        $warehouseId = $input['items'][0]['warehouse_id'];
        $itemId = $input['items'][0]['item_id'];
        $third = $D::of(1)->dividedBy(3);
        $db->table('item_warehouse')->where('item_id', $itemId)->where('warehouse_id', $warehouseId)
            ->update(['stock'=>$D::represent($third), 'calculation_snapshot'=>json_encode($D::snapshot(['stock'=>$third]))]);
        (new \Modules\Inventory\Providers\InventoryChangeServiceProvider($app))->boot();
        $app->instance('request', \Illuminate\Http\Request::create('/', 'POST', ['warehouse_id'=>$warehouseId]));
        $internal = $db->table('items')->where('id', $itemId)->value('internal_id');
        $target = '0.666666666667';
        $rows = fn()=>collect([collect(['Código','Stock real']), collect([$internal, $target])]);
        $import = new \App\Imports\StockImport();
        $import->collection($rows());
        self::assertSame(1, $import->getData()['registered']);
        $stock = \Modules\Inventory\Models\ItemWarehouse::where('item_id', $itemId)->where('warehouse_id', $warehouseId)->firstOrFail();
        self::assertSame($target, $D::represent($stock->exactValue('stock')));
        $movement = \Modules\Inventory\Models\Inventory::withoutEagerLoads()->where('item_id', $itemId)->latest('id')->firstOrFail();
        self::assertTrue($movement->exactValue('quantity')->isEqualTo($D::of($target)->minus($third)));
        $kardex = \Modules\Inventory\Models\InventoryKardex::where('inventory_kardexable_type', get_class($movement))->where('inventory_kardexable_id', $movement->id)->firstOrFail();
        self::assertTrue($kardex->exactValue('quantity')->isEqualTo($movement->exactValue('quantity')));
        $count = $db->table('inventories')->count();
        $import->collection($rows());
        self::assertSame(0, $import->getData()['registered']);
        self::assertSame(1, $import->getData()['skipped']);
        self::assertSame($count, $db->table('inventories')->count());
    }
    // ######## FIN CALCULOS EXACTOS SAB ########

    public function test_hka_emission_runs_after_outer_commit_and_recovers_without_duplicate_sales(): void
    {
        [$db, $app, $input] = $this->bootHkaFixture();
        $customer = \App\Models\Tenant\Person::findOrFail($input['customer_id']);
        $item = \App\Models\Tenant\Item::findOrFail($input['items'][0]['item_id']);
        $warehouse = $input['items'][0]['warehouse_id'];
        $establishment = $input['establishment_id'];
        $userId = $input['user_id'];
        $company = \App\Models\Tenant\Company::firstOrFail();
        $http = $app->make(\Illuminate\Http\Client\Factory::class);
        $mutations = 0; $queries = 0; $mode = 'success'; $active = null;
        $http->fake(function ($request) use ($db, &$mutations, &$queries, &$mode, &$active) {
            self::assertSame(0, $db->transactionLevel(), 'HTTP must occur after the outer commit and outside locks');
            if (str_ends_with($request->url(), '/DescargaArchivo')) {
                self::assertSame(0, $db->transactionLevel());
                $pdf = new \FPDF(); $pdf->AddPage(); $pdf->SetFont('Helvetica', '', 12); $pdf->Text(10, 15, 'HKA confirmed test');
                return \Illuminate\Support\Facades\Http::response(['codigo' => '200', 'archivo' => base64_encode($pdf->Output('S'))]);
            }
            if (str_ends_with($request->url(), '/Autenticacion')) return \Illuminate\Support\Facades\Http::response([
                'token' => 'fake-jwt-private', 'expiracion' => date('c', time() + 3600)], 200);
            if (str_ends_with($request->url(), '/EstadoDocumento')) {
                $queries++;
                if (in_array($mode, ['absent', 'resend'], true)) return \Illuminate\Support\Facades\Http::response(['codigo' => '203', 'mensaje' => 'Consulta no procesada',
                    'validaciones' => ['Documento no encontrado en nuestra base de datos'], 'estado' => null], 200);
                if ($mode === 'query-failure') return \Illuminate\Support\Facades\Http::response([], 500);
                return \Illuminate\Support\Facades\Http::response(['codigo' => '200', 'estado' => $active + ['estadoDocumento' => 'Procesado',
                    'numeroControl' => '00-'.str_pad($active['numeroDocumento'], 8, '0', STR_PAD_LEFT)]], 200);
            }
            $mutations++;
            $active = $request['documentoElectronico']['encabezado']['identificacionDocumento'];
            if ($mode === 'timeout') throw new \Illuminate\Http\Client\ConnectionException('fake-jwt-private test-secret');
            if ($mode === 'resend') {
                $inFlight = \App\Models\Tenant\Document::whereHas('emission', fn ($q) => $q->whereRaw("REPLACE(operation_key COLLATE \"C\", '-', '') = ?", [$active['transaccionId']]))->firstOrFail();
                self::assertSame('pending', app(\App\Services\Fiscal\HkaEmission::class)->send($inFlight)['status']);
            }
            return \Illuminate\Support\Facades\Http::response(['codigo' => '200', 'resultado' => $active + [
                'numeroControl' => '00-'.str_pad($active['numeroDocumento'], 8, '0', STR_PAD_LEFT)]], 200);
        });
        $service = app(\App\Services\Fiscal\HkaEmission::class);
        $db->beginTransaction();
        $fact = (new \App\CoreFacturalo\Facturalo())->save($input);
        self::assertSame(0, $mutations);
        self::assertSame('not_requested', $fact->getDocument()->emission->status);
        $db->commit();
        $document = $fact->getDocument()->fresh();
        $sentHeader = $document->emission->payload['documentoElectronico']['encabezado'];
        self::assertSame('Contado', $sentHeader['identificacionDocumento']['tipoDePago']);
        self::assertSame(['receiver@example.test'], $sentHeader['comprador']['correo']);
        self::assertSame(['04121234567'], $sentHeader['comprador']['telefono']);
        self::assertSame('No', $sentHeader['comprador']['notificar']);
        self::assertSame('confirmed', $document->emission->status);
        self::assertTrue($fact->getResponse()['sale_saved']);
        self::assertSame('00-00000100', $document->control_number);
        self::assertFalse($document->is_editable);
        $store = new \App\Services\Fiscal\HkaPdfStore();
        self::assertNotNull($store->read($document, 'a4'));
        self::assertNull($store->read($document, 'a5'));
        self::assertSame(1, $mutations);
        $router = new \Illuminate\Routing\Router(new \Illuminate\Events\Dispatcher($app), $app);
        $router->get('downloads/{model}/{type}/{external_id}/{format?}', fn () => null)->name('tenant.download.external_id');
        $router->getRoutes()->refreshNameLookups();
        $app->instance('url', new \Illuminate\Routing\UrlGenerator($router->getRoutes(), app('request')));
        // Exercise Laravel's resource wrapping, not only the DTO helper.
        $listed = (new \App\Http\Resources\Tenant\DocumentCollection(collect([$document])))->resolve();
        self::assertSame('confirmed', $listed[0]['fiscal_emission']['status']);
        self::assertSame($document->control_number, $listed[0]['fiscal_emission']['control_number']);
        self::assertTrue($listed[0]['pdf_downloads']['a4']['available']);
        self::assertSame('hka', $listed[0]['pdf_downloads']['a5']['provider']);
        self::assertStringNotContainsString('test-secret', json_encode($listed));
        $counts = [$db->table('documents')->count(), $db->table('document_payments')->count(), $db->table('inventory_kardex')->count()];
        $key = $document->emission->operation_key;
        $service->send($document); $service->send($document);
        self::assertSame(1, $mutations);
        self::assertSame($key, $document->fresh()->emission->operation_key);
        $mode = 'query-failure'; $service->query($document);
        self::assertSame('confirmed', $document->fresh()->emission->status);
        self::assertSame($counts, [$db->table('documents')->count(), $db->table('document_payments')->count(), $db->table('inventory_kardex')->count()]);

        $this->assertHkaMailDelivery($document, $db, $app, $company, $service);

        $db->beginTransaction();
        $input['external_id'] = (string) \Illuminate\Support\Str::uuid();
        (new \App\CoreFacturalo\Facturalo())->save($input);
        $db->rollBack();
        self::assertSame(1, $mutations);
        self::assertSame($counts[0], $db->table('documents')->count());

        $mode = 'timeout'; $input['external_id'] = (string) \Illuminate\Support\Str::uuid();
        $uncertain = (new \App\CoreFacturalo\Facturalo())->save($input)->getDocument()->fresh();
        self::assertSame('uncertain', $uncertain->emission->status);
        self::assertSame(2, $mutations);
        self::assertEquals(0, $uncertain->balance);
        self::assertStringNotContainsString('test-secret', json_encode($uncertain->emission->response));
        $key = $uncertain->emission->operation_key;
        $response = $uncertain->emission->response; $response['started_at'] = time() - 60;
        $uncertain->emission->update(['status' => 'pending', 'response' => $response]);
        $mode = 'absent';
        self::assertTrue($service->query($uncertain)['can_send']);
        self::assertSame('uncertain', $uncertain->fresh()->emission->status);
        $mode = 'resend';
        $result = $service->send($uncertain);
        self::assertSame('confirmed', $result['status']);
        self::assertSame(3, $mutations);
        self::assertSame($key, $uncertain->fresh()->emission->operation_key);
        self::assertSame($counts[0] + 1, $db->table('documents')->count());
        self::assertSame($counts[1] + 1, $db->table('document_payments')->count());

        $revision = \App\Services\Fiscal\DocumentEmissionView::cacheNamespace();
        $uncertain->fresh()->emission->update(['response' => ['diagnostic' => 'test']]);
        self::assertNotSame($revision, \App\Services\Fiscal\DocumentEmissionView::cacheNamespace());
        $company->fiscal_credentials = null; $company->save();
        $input['external_id'] = (string) \Illuminate\Support\Str::uuid();
        $notSent = (new \App\CoreFacturalo\Facturalo())->save($input)->getDocument()->fresh();
        self::assertSame('not_requested', $notSent->emission->status);
        self::assertSame(3, $mutations);
        self::assertStringContainsString('credenciales', $notSent->emission->response['diagnostic']);
        $this->assertPaidInvoiceEditing($notSent, $input, $db, $app);
        $input['external_id'] = (string) \Illuminate\Support\Str::uuid();
        $race = (new \App\CoreFacturalo\Facturalo)->save($input)->getDocument()->fresh();
        $company->fiscal_credentials = json_encode(['usuario' => 'test-user', 'clave' => 'test-secret']); $company->save();
        $log = tempnam(sys_get_temp_dir(), 'sab_hka_claim_');
        try {
            $results = $this->runFiscalWorkers($notSent->id, 'hka', [['log' => $log], ['log' => $log]]);
            self::assertSame("emission\n", file_get_contents($log));
            self::assertSame('confirmed', $notSent->fresh()->emission->status);
            foreach ($results as $result) self::assertContains($result['status'], ['pending', 'confirmed']);
        } finally { unlink($log); }
        $log = tempnam(sys_get_temp_dir(), 'sab_hka_edit_race_');
        try {
            $edit = array_replace($input, ['id' => $race->id, 'series' => $race->series, 'number' => $race->number, 'payments' => []]);
            $payments = $db->table('document_payments')->where('document_id', $race->id)->get()->toArray();
            $results = $this->runFiscalWorkers($race->id, 'hka-edit-race', [['action' => 'edit', 'edit' => $edit], ['action' => 'send', 'log' => $log]]);
            $status = $race->fresh()->emission->status;
            self::assertContains($status, ['confirmed', 'not_requested']);
            self::assertSame($status === 'confirmed' ? "emission\n" : '', file_get_contents($log));
            self::assertTrue(($results[0]['status'] ?? null) === 'edited' || isset($results[0]['rejected']));
            self::assertSame($status, $results[1]['status']);
            self::assertEquals($payments, $db->table('document_payments')->where('document_id', $race->id)->get()->toArray());
        } finally { unlink($log); }
        $tenantKey = \App\Services\Fiscal\DocumentEmissionView::versionKey();
        $db->setDatabaseName('another_tenant');
        self::assertNotSame($tenantKey, \App\Services\Fiscal\DocumentEmissionView::versionKey());
        $db->setDatabaseName($this->database);
        $this->assertHkaAutomaticMail($input, $db, $app);
    }

    private function assertHkaAutomaticMail(array $input, $db, $app): void
    {
        $originalHttp = app(\Illuminate\Http\Client\Factory::class);
        $http = new \Illuminate\Http\Client\Factory();
        \Illuminate\Support\Facades\Http::swap($http);
        $http->preventStrayRequests();
        $mode = 'success'; $mailMode = 'success'; $pdfMode = 'success'; $sends = 0; $emissions = 0; $active = null; $mailFacts = [];
        $http->fake(function ($request) use ($db, &$mode, &$mailMode, &$pdfMode, &$sends, &$emissions, &$active, &$mailFacts) {
            self::assertSame(0, $db->transactionLevel(), 'Automatic email cannot run inside the sale transaction');
            if (str_ends_with($request->url(), '/DescargaArchivo')) {
                self::assertSame(0, $db->transactionLevel());
                if ($pdfMode === 'failure') return \Illuminate\Support\Facades\Http::response(['codigo' => '203']);
                $pdf = new \FPDF(); $pdf->AddPage(); $pdf->SetFont('Helvetica', '', 12); $pdf->Text(10, 15, 'HKA confirmed test');
                return \Illuminate\Support\Facades\Http::response(['codigo' => '200', 'archivo' => base64_encode($pdf->Output('S'))]);
            }
            if (str_ends_with($request->url(), '/Autenticacion')) return \Illuminate\Support\Facades\Http::response([
                'token' => 'automatic-private-token', 'expiracion' => date('c', time() + 3600)]);
            if (str_ends_with($request->url(), '/Correo/Rastreo')) return \Illuminate\Support\Facades\Http::response(['codigo' => '200', 'rastreos' => []]);
            if (str_ends_with($request->url(), '/Correo/Enviar')) {
                $sends++;
                $document = \App\Models\Tenant\Document::where('series', $request['serie'])->where('number', $request['numeroDocumento'])->firstOrFail();
                $mailFacts[] = [$document->emission->status, $document->control_number, $document->emission->control_number,
                    $request['correos'], $request['tipoDocumento']];
                if ($mailMode === 'timeout') throw new \Illuminate\Http\Client\ConnectionException('automatic-private-token');
                return \Illuminate\Support\Facades\Http::response(['codigo' => $mailMode === 'rejected' ? '203' : '200']);
            }
            if (str_ends_with($request->url(), '/Emision')) {
                $emissions++;
                $active = $request['documentoElectronico']['encabezado']['identificacionDocumento'];
                if ($mode === 'timeout') throw new \Illuminate\Http\Client\ConnectionException('automatic-private-token');
            }
            return \Illuminate\Support\Facades\Http::response(['codigo' => '200',
                str_ends_with($request->url(), '/EstadoDocumento') ? 'estado' : 'resultado' => $active + [
                    'estadoDocumento' => 'Procesado', 'numeroControl' => '00-'.str_pad($active['numeroDocumento'], 8, '0', STR_PAD_LEFT)]]);
        });
        $input['customer']['email'] = ' CUSTOMER@example.test ; second@example.test,customer@example.test ';
        $originalEmail = $db->table('persons')->where('id', $input['customer_id'])->value('email');
        $db->table('persons')->where('id', $input['customer_id'])->update(['email' => $input['customer']['email']]);
        $input['actions'] = ['send_email' => false];
        $save = function () use (&$input) {
            $input['external_id'] = (string) \Illuminate\Support\Str::uuid();
            return (new \App\CoreFacturalo\Facturalo())->save($input);
        };
        try {
            $db->table('configurations')->where('id', 1)->update(['auto_send_pdf_email' => true]);
            $db->beginTransaction();
            $fact = $save();
            self::assertSame(0, $emissions); self::assertSame(0, $sends);
            self::assertSame('waiting', $fact->getDocument()->fresh()->emission->response['mail']['automatic']['status']);
            $db->commit();
            $document = $fact->getDocument()->fresh();
            self::assertSame(1, $sends);
            self::assertSame('accepted', \App\Services\Fiscal\HkaMail::view($document)['status']);
            self::assertSame(['confirmed', $document->control_number, $document->control_number,
                ['customer@example.test', 'second@example.test'], '01'], $mailFacts[0]);
            $state = [$document->control_number, $document->total, $document->balance,
                $db->table('document_payments')->count(), $db->table('inventory_kardex')->count()];
            app(\App\Services\Fiscal\HkaEmission::class)->query($document);
            app(\App\Services\Fiscal\HkaMail::class)->sendAutomatic($document);
            self::assertSame(1, $sends);
            $fresh = $document->fresh();
            self::assertSame($state, [$fresh->control_number, $fresh->total, $fresh->balance,
                $db->table('document_payments')->count(), $db->table('inventory_kardex')->count()]);
            $recipients = ['customer@example.test', 'second@example.test'];
            self::assertSame('accepted', \App\Services\Fiscal\HkaMail::automaticDelivery($fresh, $recipients)['status']);
            self::assertNull(\App\Services\Fiscal\HkaMail::automaticDelivery($fresh, ['other@example.test']));
            $request = \App\Http\Requests\Tenant\DocumentEmailRequest::create('/documents/email', 'POST', [
                'id' => $document->id, 'customer_email' => implode(',', $recipients), 'recipients' => $recipients]);
            self::assertTrue((new \App\Http\Controllers\Tenant\DocumentController)->email($request)['success']);
            self::assertSame(1, $sends, 'Existing frontend bundles must not send a second email');

            $db->beginTransaction(); $save(); $db->rollBack();
            self::assertSame(1, $sends); self::assertSame(1, $emissions);
            $mode = 'timeout';
            $pending = $save()->getDocument()->fresh();
            self::assertSame('uncertain', $pending->emission->status); self::assertSame(1, $sends);
            $mode = 'success';
            app(\App\Services\Fiscal\HkaEmission::class)->query($pending);
            self::assertSame(2, $sends, 'A later confirmation must send the saved automatic request');
            app(\App\Services\Fiscal\HkaEmission::class)->query($pending);
            self::assertSame(2, $sends);

            $mailMode = 'timeout';
            $uncertain = $save()->getDocument()->fresh();
            self::assertSame('confirmed', $uncertain->emission->status);
            self::assertSame('uncertain', \App\Services\Fiscal\HkaMail::view($uncertain)['status']);
            app(\App\Services\Fiscal\HkaEmission::class)->query($uncertain);
            self::assertSame(3, $sends, 'An uncertain email cannot be automatically retried');
            $mailMode = 'rejected';
            $rejected = $save()->getDocument()->fresh();
            self::assertSame('confirmed', $rejected->emission->status);
            self::assertSame('rejected', \App\Services\Fiscal\HkaMail::view($rejected)['status']);
            app(\App\Services\Fiscal\HkaEmission::class)->query($rejected);
            self::assertSame(4, $sends);

            $mailMode = 'success';
            foreach ([null, '', 'invalid-email'] as $email) {
                $input['customer']['email'] = $email;
                $db->table('persons')->where('id', $input['customer_id'])->update(['email' => $email]);
                $missing = $save()->getDocument()->fresh();
                self::assertSame('confirmed', $missing->emission->status);
                self::assertSame('skipped', $missing->emission->response['mail']['automatic']['status']);
                self::assertStringContainsString('correo válido', \App\Services\Fiscal\HkaMail::view($missing)['message']);
            }
            self::assertSame(4, $sends);
            $db->table('configurations')->where('id', 1)->update(['auto_send_pdf_email' => false]);
            $input['customer']['email'] = 'customer@example.test;second@example.test';
            $db->table('persons')->where('id', $input['customer_id'])->update(['email' => $input['customer']['email']]);
            $disabled = $save()->getDocument()->fresh();
            self::assertArrayNotHasKey('mail', $disabled->emission->response);
            self::assertSame(4, $sends);
            $input['actions']['send_email'] = true;
            $explicit = $save(); $explicit->sendEmail();
            self::assertSame(5, $sends, 'Explicit API email uses HKA without an SMTP duplicate');
            self::assertStringNotContainsString('automatic-private-token', json_encode(\App\Services\Fiscal\HkaMail::view($explicit->getDocument()->fresh())));
            $emission = $explicit->getDocument()->fresh()->emission;
            $response = $emission->response;
            $response['mail'] = ['automatic' => ['request_id' => (string) \Illuminate\Support\Str::uuid(),
                'recipients' => $recipients, 'status' => 'waiting']];
            $emission->update(['response' => $response]);
            $log = tempnam(sys_get_temp_dir(), 'sab_hka_auto_mail_');
            try {
                $results = $this->runFiscalWorkers($emission->document_id, 'hka-auto-mail', [['log' => $log], ['log' => $log]]);
                self::assertSame("mail\n", file_get_contents($log));
                foreach ($results as $result) self::assertContains($result['status'], ['pending', 'accepted']);
            } finally { unlink($log); }
            $pdfMode = 'failure';
            $input['actions']['send_email'] = false;
            $pdfPending = $save()->getDocument()->fresh();
            self::assertSame('confirmed', $pdfPending->emission->status, 'PDF failures preserve fiscal confirmation');
            self::assertNotEmpty($pdfPending->control_number);
            $store = app(\App\Services\Fiscal\HkaPdfStore::class);
            self::assertNull($store->read($pdfPending, 'a4'));
            $emittedBeforeRetry = $emissions;
            $paymentsBeforeRetry = $db->table('document_payments')->count();
            $pdfMode = 'success';
            app(\App\Services\Fiscal\HkaPdf::class)->download($pdfPending, 'a4');
            self::assertNotNull($store->read($pdfPending, 'a4'));
            self::assertSame($emittedBeforeRetry, $emissions, 'Downloading a missing PDF never re-emits');
            self::assertSame($paymentsBeforeRetry, $db->table('document_payments')->count());
        } finally {
            if ($db->transactionLevel()) $db->rollBack();
            $db->table('configurations')->where('id', 1)->update(['auto_send_pdf_email' => false]);
            $db->table('persons')->where('id', $input['customer_id'])->update(['email' => $originalEmail]);
            \Illuminate\Support\Facades\Http::swap($originalHttp);
        }
    }

    public function test_invoice_edit_validates_the_establishment_key_before_persisting(): void
    {
        [$db, , $input] = $this->bootHkaFixture();
        $db->table('companies')->where('id', 1)->update(['fiscal_credentials' => null]);
        $invoice = (new \App\CoreFacturalo\Facturalo())->forWebDocuments()->save($input)->getDocument();
        $establishment = (string) $invoice->establishment_id;
        $edit = array_replace($input, [
            'series' => $invoice->series,
            'number' => $invoice->number,
            'establishment_id' => $establishment,
            'payments' => [],
        ]);
        (new \App\CoreFacturalo\Facturalo())->forWebDocuments()->update($edit, $invoice->id);

        $stored = $invoice->fresh()->getRawOriginal();
        $emission = $invoice->fresh()->emission->getRawOriginal();
        foreach ([$establishment.'x', $establishment.'.0', '0'.$establishment, '9223372036854775808', null] as $invalid) {
            try {
                (new \App\CoreFacturalo\Facturalo())->forWebDocuments()->update(
                    array_replace($edit, ['establishment_id' => $invalid]), $invoice->id
                );
                self::fail('La edición debe rechazar la clave de sucursal no canónica.');
            } catch (\Illuminate\Validation\ValidationException $error) {
                self::assertArrayHasKey('establishment_id', $error->errors());
            }
        }
        self::assertSame($stored, $invoice->fresh()->getRawOriginal());
        self::assertSame($emission, $invoice->fresh()->emission->getRawOriginal());
    }

    private function assertPaidInvoiceEditing($document, array $input, $db, $app): void
    {
        $edit = array_replace($input, ['id' => $document->id, 'series' => $document->series, 'number' => $document->number,
            'payments' => [], 'received_retentions' => [], 'guarantee_fund' => null]);
        $paymentSnapshot = $db->table('document_payments')->where('document_id', $document->id)->get()->toArray();
        $identity = [$document->external_id, $document->series, $document->number, $document->issuer];
        $stock = $db->table('item_warehouse')->where('item_id', $input['items'][0]['item_id'])->value('stock');
        $db->table('persons')->where('id', $document->customer_id)->update(['address' => 'Dirección actualizada en catálogo']);
        (new \App\CoreFacturalo\Facturalo)->update($edit, $document->id);
        $fresh = $document->fresh();
        self::assertSame('Dirección actualizada en catálogo', $fresh->customer->address);
        self::assertSame('V', $fresh->customer->hka_identity_code);
        self::assertSame($identity, [$fresh->external_id, $fresh->series, $fresh->number, $fresh->issuer]);
        self::assertEquals($stock, $db->table('item_warehouse')->where('item_id', $input['items'][0]['item_id'])->value('stock'));
        self::assertEquals($paymentSnapshot, $db->table('document_payments')->where('document_id', $document->id)->get()->toArray());
        self::assertEquals(0, $fresh->balance);
        foreach (['currency' => ['currency_type_id' => 'USD'], 'number' => ['number' => $document->number + 1],
            'payment' => ['payments' => [['id' => $fresh->payments->first()->id, 'payment' => 1]]],
            'new-payment' => ['payments' => [['payment' => 1]]],
            'total' => ['items' => [array_replace($edit['items'][0], ['quantity' => 1])]]] as $case => $changes) {
            try { (new \App\CoreFacturalo\Facturalo)->update(array_replace($edit, $changes), $document->id); self::fail('Expected rejection: '.$case); }
            catch (\Illuminate\Validation\ValidationException $expected) { self::assertNotEmpty($expected->errors()); }
        }
        self::assertEquals($paymentSnapshot, $db->table('document_payments')->where('document_id', $document->id)->get()->toArray());
        foreach (['prepared', 'rejected', 'uncertain'] as $status) {
            $emission = $document->fresh()->emission;
            $uuid = $emission->operation_key;
            $emission->update(['status' => $status, 'payload' => ['old' => 'snapshot'], 'response' => ['retry_allowed' => $status === 'uncertain', 'diagnostic' => 'Diagnóstico saneado']]);
            (new \App\CoreFacturalo\Facturalo)->update($edit, $document->id);
            $emission = $document->fresh()->emission;
            self::assertSame('not_requested', $emission->status);
            self::assertNotSame($uuid, $emission->operation_key);
            self::assertNull($emission->payload);
            $history = $emission->response['edit_history'];
            self::assertSame($uuid, end($history)['operation_key']);
            $emission->update(['status' => 'pending', 'response' => array_replace($emission->response, ['attempt_id' => 'new-attempt'])]);
            $finish = new \ReflectionMethod(\App\Services\Fiscal\HkaEmission::class, 'finish');
            $finish->setAccessible(true);
            $finish->invoke(app(\App\Services\Fiscal\HkaEmission::class), $document, 'old-attempt', ['status' => 'confirmed', 'control_number' => '00-99999999'], 'send', $emission->operation_key);
            self::assertSame('pending', $document->fresh()->emission->status);
            self::assertNull($document->fresh()->control_number);
            $emission->update(['status' => 'not_requested']);
            $beforeLateFailure = $emission->fresh()->response;
            $failure = new \ReflectionMethod(\App\Services\Fiscal\HkaEmission::class, 'failure');
            $failure->setAccessible(true);
            $failure->invoke(app(\App\Services\Fiscal\HkaEmission::class), $document, null, new \RuntimeException('old-auth-failure'), $uuid);
            self::assertSame($beforeLateFailure, $emission->fresh()->response);
            try { (new \App\Services\Fiscal\HkaEmissionPreparation)->prepare($document, $uuid); self::fail('Stale preparation accepted'); }
            catch (\Illuminate\Validation\ValidationException $expected) { self::assertNotEmpty($expected->errors()); }
        }
        foreach (['pending', 'uncertain', 'confirmed', 'cancelled'] as $status) {
            $document->fresh()->emission->update(['status' => $status, 'response' => ['retry_allowed' => false]]);
            try { (new \App\CoreFacturalo\Facturalo)->update($edit, $document->id); self::fail('Expected fiscal block: '.$status); }
            catch (\Illuminate\Validation\ValidationException $expected) { self::assertNotEmpty($expected->errors()); }
        }
        $document->fresh()->emission->update(['status' => 'not_requested', 'response' => []]);
        $increase = $edit; $increase['items'][0]['unit_price'] = 120;
        (new \App\CoreFacturalo\Facturalo)->update($increase, $document->id);
        self::assertEquals(240, $document->fresh()->total);
        self::assertEquals(8, $document->fresh()->balance);
        // Edits must never send HTTP even when a digital invoice is eligible.
        self::assertEquals($paymentSnapshot, $db->table('document_payments')->where('document_id', $document->id)->get()->toArray());
        (new \App\CoreFacturalo\Facturalo)->update($edit, $document->id);
        self::assertEquals($stock, $db->table('item_warehouse')->where('item_id', $input['items'][0]['item_id'])->value('stock'));
    }

    private function assertHkaMailDelivery($document, $db, $app, $company, $emissionService): void
    {
        $originalHttp = app(\Illuminate\Http\Client\Factory::class);
        $http = new \Illuminate\Http\Client\Factory();
        \Illuminate\Support\Facades\Http::swap($http);
        $mail = app(\App\Services\Fiscal\HkaMail::class);
        $mode = 'success'; $sends = 0; $tracking = []; $inFlight = null;
        $recipients = ['mail-test@example.test'];
        $http->preventStrayRequests();
        $http->fake(function ($request) use ($db, &$mode, &$sends, &$tracking, &$inFlight, $mail, $document, $recipients) {
            self::assertSame(0, $db->transactionLevel(), 'Mail HTTP must be outside all database locks');
            if (str_ends_with($request->url(), '/DescargaArchivo')) {
                self::assertSame(0, $db->transactionLevel());
                $pdf = new \FPDF(); $pdf->AddPage(); $pdf->SetFont('Helvetica', '', 12); $pdf->Text(10, 15, 'HKA confirmed test');
                return \Illuminate\Support\Facades\Http::response(['codigo' => '200', 'archivo' => base64_encode($pdf->Output('S'))]);
            }
            if (str_ends_with($request->url(), '/Autenticacion')) return \Illuminate\Support\Facades\Http::response(
                $mode === 'auth-failure' ? [] : ['token' => 'private-mail-token', 'expiracion' => date('c', time() + 3600)]);
            if (str_ends_with($request->url(), '/Correo/Rastreo')) return \Illuminate\Support\Facades\Http::response(['codigo' => '200', 'rastreos' => $tracking]);
            if (str_ends_with($request->url(), '/EstadoDocumento')) {
                $identity = $document->emission->payload['documentoElectronico']['encabezado']['identificacionDocumento'];
                return \Illuminate\Support\Facades\Http::response(['codigo' => '200', 'estado' => $identity + ['estadoDocumento' => 'Enviada', 'numeroControl' => $document->control_number]]);
            }
            self::assertTrue(str_ends_with($request->url(), '/Correo/Enviar'));
            self::assertSame(['serie' => $document->series, 'tipoDocumento' => '01', 'numeroDocumento' => (string) $document->number, 'correos' => $recipients], $request->data());
            $sends++;
            if ($inFlight) {
                self::assertSame('pending', $mail->send($document, $recipients, $inFlight)['status']);
                self::assertSame('pending', $mail->send($document, $recipients, (string) \Illuminate\Support\Str::uuid())['status']);
            }
            if ($mode === 'timeout') throw new \Illuminate\Http\Client\ConnectionException('private-mail-token test-secret');
            return \Illuminate\Support\Facades\Http::response($mode === 'rejected' ? ['codigo' => '203', 'mensaje' => 'private-mail-token'] : ['codigo' => '200']);
        });
        try {
            $operator = auth()->user();
            $oldType = $operator->type; $oldEstablishment = $operator->establishment_id;
            $operator->type = 'seller'; $operator->establishment_id = $document->establishment_id + 100;
            $callsBefore = $http->recorded()->count();
            try {
                self::assertFalse(\App\Services\Fiscal\HkaMail::view($document)['can_send']);
                self::assertFalse(\App\Services\Fiscal\HkaMail::view($document)['can_query']);
                foreach (['query', 'send'] as $action) {
                    try {
                        if ($action === 'query') (new \App\Http\Controllers\Tenant\DocumentFiscalController)->queryHkaEmail($document->id);
                        else (new \App\Http\Controllers\Tenant\DocumentController)->email(\App\Http\Requests\Tenant\DocumentEmailRequest::create('/documents/email', 'POST', ['id' => $document->id]));
                        self::fail('Another establishment must be forbidden');
                    } catch (\Symfony\Component\HttpKernel\Exception\HttpException $expected) { self::assertSame(403, $expected->getStatusCode()); }
                }
                self::assertSame($callsBefore, $http->recorded()->count());
            } finally { $operator->type = $oldType; $operator->establishment_id = $oldEstablishment; }
            $before = [$db->table('documents')->count(), $db->table('document_payments')->count(), $db->table('inventory_kardex')->count(),
                $document->control_number, $document->emission->operation_key, $document->emission->payload];
            $inFlight = (string) \Illuminate\Support\Str::uuid();
            $firstId = $inFlight;
            $revision = \App\Services\Fiscal\DocumentEmissionView::cacheNamespace();
            self::assertSame('accepted', $mail->send($document, $recipients, $firstId)['status']);
            self::assertSame('accepted', $mail->send($document, $recipients, $firstId)['status']);
            self::assertSame(1, $sends);
            self::assertNotSame($revision, \App\Services\Fiscal\DocumentEmissionView::cacheNamespace());
            $inFlight = null;
            try { $mail->send($document, $recipients, (string) \Illuminate\Support\Str::uuid()); self::fail('Explicit resend must be required'); }
            catch (\Illuminate\Validation\ValidationException $expected) { self::assertArrayHasKey('customer_email', $expected->errors()); }
            $mode = 'rejected';
            self::assertSame('rejected', $mail->send($document, $recipients, (string) \Illuminate\Support\Str::uuid(), true)['status']);
            $mode = 'auth-failure'; \Illuminate\Support\Facades\Cache::flush();
            self::assertSame('rejected', $mail->send($document, $recipients, (string) \Illuminate\Support\Str::uuid())['status']);
            self::assertSame(2, $sends);
            $mode = 'timeout';
            $uncertainId = (string) \Illuminate\Support\Str::uuid();
            self::assertSame('uncertain', $mail->send($document, $recipients, $uncertainId)['status']);
            self::assertFalse(\App\Services\Fiscal\HkaMail::view($document->fresh())['can_send']);
            self::assertSame('uncertain', $mail->send($document, $recipients, (string) \Illuminate\Support\Str::uuid())['status']);
            self::assertSame(3, $sends);
            $mode = 'success';
            self::assertSame('uncertain', $mail->query($document)['status']);
            $tracking = [['messageId' => 'new-mail-id', 'correo' => $recipients[0], 'status' => 'delivered', 'fecha' => date('c')],
                ['messageId' => 'other-mail-id', 'correo' => 'other@example.test', 'status' => 'delivered', 'fecha' => date('c')]];
            self::assertSame('accepted', $mail->query($document)['status']);
            self::assertCount(1, $mail->query($document)['tracking']);
            $response = $document->fresh()->emission->response;
            $response['mail']['attempts'][$uncertainId]['status'] = 'pending';
            $response['mail']['attempts'][$uncertainId]['started_at'] = time() - 60;
            $document->fresh()->emission->update(['response' => $response]);
            self::assertSame('accepted', $mail->query($document)['status']);
            $emissionService->query($document);
            self::assertSame($uncertainId, $document->fresh()->emission->response['mail']['latest']);
            self::assertSame('confirmed', $document->fresh()->emission->status);
            self::assertStringNotContainsString('private-mail-token', json_encode(\App\Services\Fiscal\HkaMail::view($document->fresh())));
            self::assertStringNotContainsString('test-secret', json_encode($document->fresh()->emission->response));
            $after = $document->fresh();
            self::assertSame($before, [$db->table('documents')->count(), $db->table('document_payments')->count(), $db->table('inventory_kardex')->count(),
                $after->control_number, $after->emission->operation_key, $after->emission->payload]);
            $fiscalStatus = $after->emission->status;
            $after->emission->update(['status' => 'prepared']);
            self::assertFalse(\App\Services\Fiscal\HkaMail::view($after->fresh())['can_send']);
            try { $mail->send($after, $recipients, (string) \Illuminate\Support\Str::uuid()); self::fail('Unconfirmed invoice must be blocked'); }
            catch (\Illuminate\Validation\ValidationException $expected) { self::assertArrayHasKey('customer_email', $expected->errors()); }
            $after->emission->update(['status' => $fiscalStatus]);
            // Two independent PostgreSQL connections must still produce only one email mutation.
            $mailResponse = $after->fresh()->emission->response;
            unset($mailResponse['mail']); $after->fresh()->emission->update(['response' => $mailResponse]);
            $log = tempnam(sys_get_temp_dir(), 'sab_hka_mail_');
            try {
                $uuid = (string) \Illuminate\Support\Str::uuid();
                $results = $this->runFiscalWorkers($after->id, 'hka-mail', [['log' => $log, 'uuid' => $uuid], ['log' => $log, 'uuid' => $uuid]]);
                self::assertSame("mail\n", file_get_contents($log));
                foreach ($results as $result) self::assertContains($result['status'], ['pending', 'accepted']);
            } finally { unlink($log); }
        } finally {
            \Illuminate\Support\Facades\Http::swap($originalHttp);
        }
    }

    private function assertTransferReasonPersistence(Connection $db): void
    {
        $db->beginTransaction();
        try {
            $establishmentId = $db->table('establishments')->insertGetId([
                'description' => 'Establecimiento motivos de traslado', 'country_id' => 'VE',
                'department_id' => '14', 'province_id' => '0229', 'district_id' => '000619',
                'address' => 'Dirección de prueba', 'telephone' => '04121234567', 'code' => '0099',
            ]);
            $userId = $db->table('users')->insertGetId([
                'name' => 'Usuario motivos de traslado', 'email' => 'transfer-reasons@example.test',
                'password' => 'not-a-login-hash', 'type' => 'admin', 'establishment_id' => $establishmentId,
            ]);

            foreach (['04', '21', '22', '23', '24'] as $index => $reasonId) {
                $db->table('dispatches')->insert([
                    'user_id' => $userId,
                    'external_id' => sprintf('00000000-0000-0000-0000-%012d', $index + 1),
                    'establishment_id' => $establishmentId,
                    'establishment' => json_encode(['id' => $establishmentId, 'description' => 'Establecimiento motivos de traslado']),
                    'fiscal_environment' => 'demo',
                    'state_type_id' => '01',
                    'ubl_version' => '2.1',
                    'document_type_id' => '09',
                    'series' => 'OE',
                    'number' => $index + 1,
                    'date_of_issue' => '2026-09-13',
                    'time_of_issue' => '12:00:00',
                    'transport_mode_type_id' => '02',
                    'transfer_reason_type_id' => $reasonId,
                    'transfer_reason_description' => $reasonId === '24' ? 'Causa especial de prueba' : null,
                    'date_of_shipping' => '2026-09-13',
                    'transshipment_indicator' => 0,
                    'unit_type_id' => 'KG',
                    'total_weight' => 1,
                ]);
            }

            self::assertSame(
                ['04', '21', '22', '23', '24'],
                $db->table('dispatches')->orderBy('number')->pluck('transfer_reason_type_id')->all()
            );
            self::assertSame('Causa especial de prueba', $db->table('dispatches')->where('transfer_reason_type_id', '24')->value('transfer_reason_description'));
        } finally {
            $db->rollBack();
        }
    }

    private function assertProductAndVariationPersistence(Connection $db): void
    {
        $db->beginTransaction();
        try {
            $establishmentId = $db->table('establishments')->insertGetId([
                'description' => 'Establecimiento de prueba', 'country_id' => 'VE',
                'department_id' => '14', 'province_id' => '0229', 'district_id' => '000619',
                'address' => 'Dirección de prueba', 'telephone' => '04121234567', 'code' => '0000',
            ]);
            $warehouseId = $db->table('warehouses')->insertGetId([
                'establishment_id' => $establishmentId, 'description' => 'Almacén de prueba',
            ]);
            $service = \App\Models\Tenant\Item::where('internal_id', 'DELIVERY-ECOM')->firstOrFail();
            $newService = $service->replicate();
            $newService->internal_id = 'TEST-SERVICE';
            $newService->description = 'Servicio de prueba';
            $newService->save();
            $parent = $service->replicate();
            $parent->fill([
                'internal_id' => 'TEST-PARENT', 'description' => 'Producto de prueba',
                'unit_type_id' => 'UND', 'sale_unit_price' => 116,
                'sale_affectation_igv_type_id' => '10', 'purchase_affectation_igv_type_id' => '10',
            ]);
            $parent->save();
            $variation = $parent->replicate();
            $variation->internal_id = 'TEST-VARIATION';
            $parent->variations()->save($variation);

            $variable = \Modules\Item\Models\ProductVariable::create([
                'name' => 'Talla prueba', 'value_type' => 'list', 'active' => true,
            ]);
            $value = $variable->values()->create(['value' => 'M', 'position' => 1, 'active' => true]);
            $assignment = \Modules\Item\Models\ItemVariationValue::create([
                'item_id' => $variation->id, 'product_variable_id' => $variable->id,
                'product_variable_value_id' => $value->id,
            ]);

            $warehouse = \App\Models\Tenant\ItemWarehouse::create([
                'item_id' => $variation->id, 'warehouse_id' => $warehouseId, 'stock' => 5,
            ]);
            $warehouse->addStock(-2)->save();
            $loaded = \App\Models\Tenant\Item::withCount('variations')->findOrFail($parent->id);
            self::assertSame(1, $loaded->variations_count);
            self::assertSame($parent->id, $variation->parent->id);
            self::assertSame($value->id, $assignment->value->id);
            self::assertSame($variable->id, $assignment->variable->id);
            self::assertEquals(3, $variation->fresh()->warehouses->first()->stock);
            self::assertEquals(116, $loaded->sale_unit_price);
            self::assertSame('10', $loaded->sale_affectation_igv_type_id);
            self::assertSame('SERV', $service->unit_type_id);
            self::assertSame('SERV', $newService->fresh()->unit_type_id);
        } finally {
            $db->rollBack();
        }
        self::assertFalse($db->table('items')->whereIn('internal_id', ['TEST-PARENT', 'TEST-VARIATION', 'TEST-SERVICE'])->exists());
    }

    public function test_retired_regime_tenant_migration_preserves_history_and_custom_operations(): void
    {
        $db = $this->capsule->getConnection('tenant');
        $db->statement('CREATE TABLE establishments (id integer PRIMARY KEY, template_pdf text, template_ticket_pdf text)');
        $db->statement('CREATE TABLE configurations (id integer PRIMARY KEY, plan json, formats text)');
        $db->statement('CREATE TABLE format_templates (id serial PRIMARY KEY, formats text, urls json, is_custom_ticket boolean DEFAULT false)');
        $db->statement('CREATE TABLE cat_operation_types (id text PRIMARY KEY, active boolean)');
        $db->statement('CREATE TABLE historical_invoices (id integer PRIMARY KEY, operation_type_id text REFERENCES cat_operation_types(id))');
        $db->table('establishments')->insert(['id'=>1, 'template_pdf'=>'default', 'template_ticket_pdf'=>'nrus']);
        $db->table('configurations')->insert(['id'=>1, 'plan'=>json_encode(['module_permissions'=>['nrus'=>true, 'modules'=>[1]]]), 'formats'=>'nrus']);
        $db->table('cat_operation_types')->insert([
            ['id'=>'0101','active'=>false], ['id'=>'0201','active'=>false],
            ['id'=>'0202','active'=>false], ['id'=>'0203','active'=>false], ['id'=>'0113','active'=>true],
        ]);
        $db->table('historical_invoices')->insert(['id'=>1, 'operation_type_id'=>'0113']);
        $migration = require database_path('migrations/tenant/2026_11_19_120000_remove_legacy_nrus.php');
        $migration->up();
        $migration->up();
        self::assertSame('default', $db->table('establishments')->value('template_ticket_pdf'));
        self::assertSame('default', $db->table('configurations')->value('formats'));
        self::assertArrayNotHasKey('nrus', json_decode($db->table('configurations')->value('plan'), true)['module_permissions']);
        self::assertSame(['0101','0201','0202','0203'], $db->table('cat_operation_types')->where('active', true)->orderBy('id')->pluck('id')->all());
        self::assertSame('0113', $db->table('historical_invoices')->value('operation_type_id'));
        self::assertSame(1, $db->table('format_templates')->where('formats', 'default')->count());
    }

    public function test_retired_regime_central_migration_stops_for_assigned_clients(): void
    {
        $db = $this->capsule->getConnection('tenant');
        $db->statement('CREATE TABLE plans (id integer PRIMARY KEY, name text, locked boolean, module_permissions json)');
        $db->statement('CREATE TABLE clients (id integer PRIMARY KEY, plan_id integer REFERENCES plans(id))');
        $db->statement('CREATE TABLE payment_orders (id integer PRIMARY KEY, plan_id integer REFERENCES plans(id) ON DELETE SET NULL, order_state_id integer)');
        $db->statement('CREATE TABLE configurations (id integer PRIMARY KEY, guest_register_plan_id integer)');
        $db->statement('CREATE TABLE business_turns (id integer PRIMARY KEY, value text)');
        $db->table('plans')->insert(['id'=>1, 'name'=>'NRUS', 'locked'=>true, 'module_permissions'=>'{}']);
        $db->table('clients')->insert(['id'=>1, 'plan_id'=>1]);
        $migration = require database_path('migrations/2026_11_19_120000_remove_legacy_nrus.php');
        try {
            $migration->up();
            self::fail('La migración debía detenerse ante un cliente asignado.');
        } catch (\RuntimeException $error) {
            self::assertStringContainsString('clientes', $error->getMessage());
        }
        self::assertSame(1, $db->table('plans')->count());
        $db->table('clients')->delete();
        $db->table('configurations')->insert(['id'=>1, 'guest_register_plan_id'=>1]);
        $db->table('payment_orders')->insert(['id'=>1, 'plan_id'=>1, 'order_state_id'=>2]);
        $migration->up();
        $migration->up();
        self::assertSame(0, $db->table('plans')->count());
        self::assertNull($db->table('configurations')->value('guest_register_plan_id'));
        self::assertNull($db->table('payment_orders')->value('plan_id'));
    }

    public function test_fresh_system_migrations_do_not_create_fiscal_transport_configuration(): void
    {
        $this->capsule->getDatabaseManager()->setDefaultConnection('system');
        // Include the module migrations registered by the real central installer.
        $migrations=$this->migrations('migrations/*.php','../modules/Marketplace/Database/Migrations/*.php');
        foreach ($migrations as $migration) {
            $migration->up();
        }
        $db = $this->capsule->getConnection('system');
        self::assertTrue($db->getSchemaBuilder()->hasTable('configurations'));
        $this->assertNoTransportColumns($db);
        self::assertFalse($db->getSchemaBuilder()->hasColumn('configurations', 'fiscal_environment'));
        foreach (['xml_link', 'cdr_link', 'estado_sunat', 'mensaje_sunat'] as $column) {
            self::assertFalse($db->getSchemaBuilder()->hasColumn('massive_invoices', $column), $column);
        }
        foreach (['estado_emision', 'mensaje_emision'] as $column) {
            self::assertTrue($db->getSchemaBuilder()->hasColumn('massive_invoices', $column), $column);
        }
        self::assertSame(0, $db->table('module_levels')->whereIn('value', ['dispatch_carrier', 'carrier_dispatches'])->count());
        self::assertSame(1, $db->table('module_levels')->where('value', 'dispatches')->count());
        foreach (array_reverse($migrations) as $migration) $migration->down();
        self::assertSame([],array_map(fn($row)=>(array)$row,\App\Database\SchemaInspector::tables($db)));
        foreach ($migrations as $migration) $migration->up();
        foreach (require base_path('config/exact_calculations_system.php') as $table=>$fields) {
            self::assertTrue($db->getSchemaBuilder()->hasColumn($table,'calculation_snapshot'),$table);
            foreach ($fields as $field) {
                $expected=$table==='plans' && $field==='sales_limit' ? 'numeric(32,12)' : 'numeric(30,12)';
                $column=$db->selectOne('SELECT format_type(a.atttypid,a.atttypmod) AS definition FROM pg_attribute a JOIN pg_class tbl ON tbl.oid=a.attrelid JOIN pg_namespace ns ON ns.oid=tbl.relnamespace WHERE ns.nspname=current_schema() AND tbl.relname = ? AND a.attname = ?',[$table,$field]);
                self::assertSame($expected,$column->definition,$table.'.'.$field);
            }
        }
    }

    private function assertFiscalSchema(Connection $db): void
    {
        self::assertSame(['demo', 'production'], $db->table('fiscal_environments')->orderBy('id')->pluck('id')->all());
        // ######## INICIO CONTRATO UNIDADES DE MEDIDA VENEZUELA ########
        self::assertSame([
            'BOL', 'BOT', 'BTO', 'CAJ', 'CM', 'DIA', 'DOC', 'GAL', 'GR', 'HR', 'JGO', 'KG',
            'KM', 'LB', 'LT', 'M', 'M2', 'M3', 'MG', 'ML', 'MM', 'PAR', 'PQT', 'PULG',
            'SAC', 'SERV', 'TON', 'UND',
        ], $db->table('cat_unit_types')->orderBy('id')->pluck('id')->all());
        self::assertSame(28, $db->table('cat_unit_types')->where('active', 1)->count());
        self::assertSame(28, $db->table('cat_unit_types')->whereColumn('id', 'symbol')->count());
        self::assertSame(0, $db->table('cat_unit_types')->whereNull('hka_code')->count());
        self::assertSame(28, $db->table('cat_unit_types')->distinct()->count('hka_code'));
        self::assertSame('XBG', $db->table('cat_unit_types')->where('id', 'BOL')->value('hka_code'));
        self::assertSame('XBE', $db->table('cat_unit_types')->where('id', 'BTO')->value('hka_code'));
        self::assertSame('PR', $db->table('cat_unit_types')->where('id', 'PAR')->value('hka_code'));
        $hkaColumn = $db->selectOne('SELECT IS_NULLABLE AS nullable, DATA_TYPE AS type, CHARACTER_MAXIMUM_LENGTH AS length, COLUMN_DEFAULT AS default_value FROM information_schema.COLUMNS WHERE table_catalog = ? AND table_schema=current_schema() AND TABLE_NAME = ? AND COLUMN_NAME = ?', [$this->database, 'cat_unit_types', 'hka_code']);
        self::assertSame('NO', $hkaColumn->nullable);
        self::assertSame('character varying', $hkaColumn->type);
        self::assertSame(3, (int) $hkaColumn->length);
        self::assertNull($hkaColumn->default_value);
        self::assertSame(0, $db->table('cat_unit_types')->whereIn('id', ['NIU', 'ZZ'])->count());
        $unitController = new \App\Http\Controllers\Tenant\UnitTypeController();
        $unitRequest = \Illuminate\Http\Request::create('/unit_types/records', 'GET', ['active' => '1']);
        self::assertCount(28, $unitController->records($unitRequest)->toArray($unitRequest));
        self::assertSame('XBG', $unitController->record('BOL')->toArray($unitRequest)['hka_code']);
        self::assertFalse($unitController->active(\Illuminate\Http\Request::create('/unit_types/active', 'POST', ['id' => 'UND', 'active' => false]))['success']);
        self::assertTrue($unitController->active(\Illuminate\Http\Request::create('/unit_types/active', 'POST', ['id' => 'BOL', 'active' => false]))['success']);
        self::assertCount(27, $unitController->records($unitRequest)->toArray($unitRequest));
        self::assertSame('XBG', $unitController->record('BOL')->toArray($unitRequest)['hka_code']);
        self::assertTrue($unitController->active(\Illuminate\Http\Request::create('/unit_types/active', 'POST', ['id' => 'BOL', 'active' => true]))['success']);
        self::assertCount(28, $unitController->records($unitRequest)->toArray($unitRequest));
        // ######## FIN CONTRATO UNIDADES DE MEDIDA VENEZUELA ########
        $this->assertNoTransportColumns($db);
        foreach (['companies', 'documents', 'fiscal_configuration_audits'] as $table) {
            $column = $db->selectOne('SELECT IS_NULLABLE AS nullable, CHARACTER_MAXIMUM_LENGTH AS length FROM information_schema.COLUMNS WHERE table_catalog = ? AND table_schema=current_schema() AND TABLE_NAME = ? AND COLUMN_NAME = ?', [$this->database, $table, 'fiscal_emission_mode']);
            self::assertSame('NO', $column->nullable, $table);
            self::assertSame(32, (int) $column->length, $table);
        }
        foreach (['fiscal_configuration', 'fiscal_credentials'] as $name) {
            $column = $db->selectOne('SELECT IS_NULLABLE AS nullable, DATA_TYPE AS type FROM information_schema.COLUMNS WHERE table_catalog = ? AND table_schema=current_schema() AND TABLE_NAME = ? AND COLUMN_NAME = ?', [$this->database, 'companies', $name]);
            self::assertSame('YES', $column->nullable);
            self::assertSame('text', $column->type);
        }
        $hkaAuthenticated = $db->selectOne('SELECT IS_NULLABLE AS nullable, DATA_TYPE AS type FROM information_schema.COLUMNS WHERE table_catalog = ? AND table_schema=current_schema() AND TABLE_NAME = ? AND COLUMN_NAME = ?', [$this->database, 'companies', 'hka_authenticated_at']);
        self::assertSame('YES', $hkaAuthenticated->nullable);
        self::assertSame('timestamp without time zone', $hkaAuthenticated->type);
        self::assertNotNull($db->selectOne('SELECT con.conname FROM pg_constraint con JOIN pg_class tbl ON tbl.oid=con.conrelid JOIN pg_namespace ns ON ns.oid=tbl.relnamespace JOIN pg_class rt ON rt.oid=con.confrelid CROSS JOIN LATERAL generate_subscripts(con.conkey,1) k(i) JOIN pg_attribute a ON a.attrelid=tbl.oid AND a.attnum=con.conkey[k.i] JOIN pg_attribute ra ON ra.attrelid=rt.oid AND ra.attnum=con.confkey[k.i] WHERE ns.nspname=current_schema() AND current_database() = ? AND tbl.relname = ? AND a.attname = ? AND rt.relname = ?', [$this->database, 'fiscal_configuration_audits', 'company_id', 'companies']));
        foreach (['digital_certificate_qztray', 'private_certificate_qztray'] as $column) {
            self::assertTrue($db->getSchemaBuilder()->hasColumn('companies', $column));
        }
        self::assertSame(0, $db->table('format_templates')->where('formats', 'legend_amazonia')->count());
        self::assertSame(0, $db->table('system_activity_log_types')->where('id', 'like', 'companies_soap_%')->count());
    }

    private function assertNoTransportColumns(Connection $db): void
    {
        self::assertFalse($db->getSchemaBuilder()->hasTable('soap_types'));
        foreach (['has_advanced_statuses', 'legend_footer', 'legend_forest_to_xml', 'default_document_type_03', 'name_product_pdf_to_xml', 'send_auto', 'sunat_alternate_server', 'auto_send_dispatchs_to_sunat'] as $column) {
            self::assertFalse($db->getSchemaBuilder()->hasColumn('configurations', $column), $column);
        }
        self::assertFalse($db->getSchemaBuilder()->hasColumn('companies', 'operation_amazonia'));
        foreach (['sire_client_id', 'sire_client_secret', 'sire_username', 'sire_password'] as $column) {
            self::assertFalse($db->getSchemaBuilder()->hasColumn('companies', $column), $column);
        }
        self::assertFalse($db->getSchemaBuilder()->hasColumn('document_items', 'name_product_xml'));
        self::assertFalse($db->getSchemaBuilder()->hasColumn('configuration_mi_tienda_pe', 'series_document_bt_id'));
        foreach ([
            'documents' => ['hash', 'qr', 'has_xml', 'has_cdr', 'send_server', 'shipping_status', 'sunat_shipping_status', 'query_status', 'success_shipping_status', 'success_sunat_shipping_status', 'success_query_status', 'regularize_shipping', 'response_regularize_shipping'],
            'dispatches' => ['sunat_error_response', 'has_xml', 'has_cdr', 'ticket', 'reception_date'],
            'perceptions' => ['hash', 'has_xml', 'has_cdr'],
            'retentions' => ['hash', 'has_xml', 'has_cdr'],
            'purchase_settlements' => ['hash', 'has_xml', 'has_cdr'],
            'voided' => ['ticket', 'has_ticket', 'has_cdr'],
        ] as $table => $columns) {
            foreach ($columns as $column) {
                self::assertFalse($db->getSchemaBuilder()->hasColumn($table, $column), "{$table}.{$column}");
            }
        }
        if ($db->getSchemaBuilder()->hasTable('module_levels')) {
            self::assertSame(0, $db->table('module_levels')->whereIn('value', ['document_not_sent', 'regularize_shipping'])->count());
        }
        self::assertSame([], $db->select("SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.COLUMNS WHERE table_catalog = ? AND table_schema=current_schema() AND (COLUMN_NAME LIKE 'soap_%' OR COLUMN_NAME IN ('certificate', 'certificate_due', 'send_to_pse', 'response_signature_pse', 'response_send_cdr_pse', 'config_system_env'))", [$this->database]));
    }

    // ######## INICIO INTEGRIDAD DE INSTALACIÓN NUEVA ########
    private function assertForeignKeyIntegrity(Connection $db): void
    {
        $keys = \App\Database\SchemaInspector::foreignKeys($db);
        foreach (collect($keys)->groupBy(fn ($key) => $key->table . '.' . $key->name) as $name => $columns) {
            $first = $columns->first();
            $query = $db->table($first->table . ' as child')->leftJoin($first->reference_table . ' as parent', function ($join) use ($columns) {
                foreach ($columns as $column) {
                    $join->on('child.' . $column->column, '=', 'parent.' . $column->reference_column);
                }
            });
            foreach ($columns as $column) {
                $query->whereNotNull('child.' . $column->column);
            }
            self::assertSame(0, $query->whereNull('parent.' . $first->reference_column)->count(), $name);
        }
    }
    // ######## FIN INTEGRIDAD DE INSTALACIÓN NUEVA ########

    private function schemaSnapshot(Connection $db): array
    {
        return \App\Database\SchemaInspector::snapshot($db);
    }

    private function seedSnapshot(Connection $db, bool $normalizeTimestamps = true): array
    {
        $snapshot = [];
        foreach (\App\Database\SchemaInspector::tables($db) as $tableRow) {
            $table = $tableRow;
            $rows = [];
            foreach ($db->table($table)->get() as $row) {
                $values = (array) $row;
                // Only generated audit timestamps may differ between clean seeding cycles.
                foreach (['created_at', 'updated_at'] as $column) {
                    if ($normalizeTimestamps && isset($values[$column])) {
                        $values[$column] = '<generated timestamp>';
                    }
                }
                $rows[] = json_encode($values);
            }
            sort($rows, SORT_STRING);
            $snapshot[$table] = $rows;
        }
        ksort($snapshot);
        return $snapshot;
    }
}
// ######## FIN MODALIDAD DE EMISIÓN FISCAL ########
