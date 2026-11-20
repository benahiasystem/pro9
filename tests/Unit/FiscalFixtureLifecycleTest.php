<?php

namespace Tests\Unit;

use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Facade;
use PHPUnit\Framework\TestCase;

/** @group postgres-integration */
final class FiscalFixtureLifecycleTest extends TestCase
{
    public function test_completed_disconnected_fixture_releases_its_owned_capsule_graph(): void
    {
        $fixture=(new \ReflectionClass(FiscalEmissionSchemaTest::class))->newInstanceWithoutConstructor();
        $capsule=new Manager(new Container());
        self::assertSame([],$capsule->getDatabaseManager()->getConnections());
        $reference=\WeakReference::create($capsule);
        (new \ReflectionProperty($fixture,'capsule'))->setValue($fixture,$capsule);
        $capsule=null;
        (new \ReflectionMethod($fixture,'tearDown'))->invoke($fixture);
        gc_collect_cycles();
        self::assertNull($reference->get(),'A completed PHPUnit fixture must not retain its application/connection graph.');
    }

    public function test_native_fixture_drops_only_its_temporary_database_restores_globals_and_releases_its_graph(): void
    {
        $previous=[Container::getInstance(),Facade::getFacadeApplication(),Model::getConnectionResolver(),Model::getEventDispatcher()];
        $fixture=new class extends FiscalEmissionSchemaTest {
            public function start(): void {parent::setUp();}
            public function stop(): void {parent::tearDown();}
        };
        $stopped=false;
        try {
            $fixture->start();
            $capsule=(new \ReflectionProperty(FiscalEmissionSchemaTest::class,'capsule'))->getValue($fixture);
            $db=$capsule->getConnection('tenant');
            \Tests\Support\PostgresTestEnvironment::assertServer($db);
            $name=$db->getDatabaseName();
            self::assertMatchesRegularExpression('/^pro9_fiscal_test_[a-f0-9]{12}$/D',$name);
            $control=$capsule->getConnection('control')->getPdo();
            $capsuleReference=\WeakReference::create($capsule);
            $appReference=\WeakReference::create(Container::getInstance());
            unset($capsule,$db);
            $fixture->stop();$stopped=true;
            gc_collect_cycles();
            self::assertSame($previous,[Container::getInstance(),Facade::getFacadeApplication(),Model::getConnectionResolver(),Model::getEventDispatcher()]);
            self::assertSame(getenv('PRO9_POSTGRES_RUN_ID'),$control->query("SELECT current_setting('pro9.test_run',true)")->fetchColumn());
            $statement=$control->prepare('SELECT count(*) FROM pg_catalog.pg_database WHERE datname=?');$statement->execute([$name]);
            self::assertSame(0,(int)$statement->fetchColumn());
            self::assertNull($capsuleReference->get(),'The ended test case must release its owned Capsule.');
            self::assertNull($appReference->get(),'The ended test case must release its owned application.');
        } finally {if(!$stopped)$fixture->stop();}
    }
}
