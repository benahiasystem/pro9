<?php

namespace Tests\Support;

use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Facade;

/** Own one empty database on the runner's marked PostgreSQL cluster. */
final class AccountingPostgresFixture
{
    private $previousContainer;
    private $previousFacade;
    private $previousResolver;
    private $previousDispatcher;
    private string $previousTimezone;
    private ?Manager $manager = null;
    private ?string $database = null;
    private ?string $storage = null;
    private bool $closed = false;

    public function start(bool $installAccounting = true): Application
    {
        $this->previousContainer = Container::getInstance();
        $this->previousFacade = Facade::getFacadeApplication();
        $this->previousResolver = Model::getConnectionResolver();
        $this->previousDispatcher = Model::getEventDispatcher();
        $this->previousTimezone = date_default_timezone_get();
        try {
            $connection = PostgresTestEnvironment::connection();
            $app = new Application(dirname(__DIR__, 2));
            Container::setInstance($app);
            Facade::clearResolvedInstances();
            Facade::setFacadeApplication($app);
            $app->instance('config', new \Illuminate\Config\Repository([
                'app' => ['env' => 'testing', 'timezone' => 'America/Caracas'],
                'accounting' => ['demo' => true], 'database' => ['default' => 'tenant'],
            ]));
            $app->instance('date', new \Illuminate\Support\DateFactory());
            $events = new \Illuminate\Events\Dispatcher($app);
            $app->instance('events', $events);
            $app->instance('validator', new \Illuminate\Validation\Factory(
                new \Illuminate\Translation\Translator(new \Illuminate\Translation\ArrayLoader(), 'es'), $app));
            $this->manager = new Manager($app);
            $this->manager->addConnection($connection, 'control');
            $control = $this->manager->getConnection('control');
            PostgresTestEnvironment::assertServer($control);
            $name = 'sab_fiscal_test_'.bin2hex(random_bytes(6));
            $control->statement('CREATE DATABASE "'.$name.'" TEMPLATE template0');
            $this->database = $name;
            FiscalTemporaryDatabases::register($name, $control->getPdo());
            $connection['database'] = $name;
            $this->manager->addConnection($connection, 'tenant');
            $this->manager->getDatabaseManager()->setDefaultConnection('tenant');
            $this->manager->setEventDispatcher($events);
            Model::clearBootedModels();
            $this->manager->bootEloquent();
            $app->instance('db', $this->manager->getDatabaseManager());
            $db = $this->manager->getConnection('tenant');
            PostgresTestEnvironment::assertServer($db);
            \App\Database\PostgresBootstrap::install($db);
            $db->setTransactionManager(new \Illuminate\Database\DatabaseTransactionsManager());
            $app->instance(\Hyn\Tenancy\Database\Connection::class, new class extends \Hyn\Tenancy\Database\Connection {
                public function __construct() {}
                public function tenantName(): string { return 'tenant'; }
                public function systemName(): string { return 'tenant'; }
            });
            $app->singleton(\Modules\Accounting\Domain\CompanyContext::class, fn() => new \Modules\Accounting\Domain\CompanyContext());
            $this->storage = sys_get_temp_dir().'/'.$name.'-accounting';
            if (!mkdir($this->storage, 0700)) throw new \RuntimeException('No se pudo preparar el almacenamiento privado de prueba.');
            $app->useStoragePath($this->storage);
            date_default_timezone_set('America/Caracas');
            if ($installAccounting) {
                $schema = $db->getSchemaBuilder();
                $schema->create('users', function ($t): void {
                    $t->bigIncrements('id'); $t->string('name'); $t->string('email')->unique();
                    $t->string('password'); $t->string('type')->default('admin');
                    $t->boolean('active')->default(true); $t->boolean('locked')->default(false);
                    $t->string('api_token')->nullable(); $t->timestamps();
                });
                \Modules\Accounting\Tests\NativeSourceTestSchema::create($schema);
                foreach (glob(dirname(__DIR__, 2).'/modules/Accounting/Database/Migrations/*.php') as $file) {
                    $migration = require $file;
                    $db->transaction(static fn() => $migration->up());
                }
            }
            return $app;
        } catch (\Throwable $error) {
            $this->close();
            throw $error;
        }
    }

    public function close(): void
    {
        if ($this->closed) return;
        $this->closed = true;
        try {
            if ($this->database !== null && $this->manager !== null) {
                foreach (array_keys($this->manager->getDatabaseManager()->getConnections()) as $name) {
                    if ($name !== 'control') $this->manager->getDatabaseManager()->purge($name);
                }
                $control = $this->manager->getConnection('control');
                PostgresTestEnvironment::assertServer($control);
                $control->statement('DROP DATABASE "'.$this->database.'" WITH (FORCE)');
                FiscalTemporaryDatabases::forget($this->database);
            }
        } finally {
            if ($this->storage !== null && is_dir($this->storage)) {
                (new \Illuminate\Filesystem\Filesystem())->deleteDirectory($this->storage);
            }
            $this->manager?->getDatabaseManager()->purge('control');
            Model::clearBootedModels();
            if ($this->previousResolver) Model::setConnectionResolver($this->previousResolver);
            else Model::unsetConnectionResolver();
            if ($this->previousDispatcher) Model::setEventDispatcher($this->previousDispatcher);
            else Model::unsetEventDispatcher();
            Facade::clearResolvedInstances();
            Facade::setFacadeApplication($this->previousFacade);
            Container::setInstance($this->previousContainer);
            date_default_timezone_set($this->previousTimezone);
        }
    }
}
