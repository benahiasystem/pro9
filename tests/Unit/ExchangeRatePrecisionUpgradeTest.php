<?php
namespace Tests\Unit;
use App\Services\ExchangeRates\ExchangeRatePrecisionUpgrade;
use Illuminate\Support\Facades\DB;
use Tests\Support\PostgresTestEnvironment;

// ########## INICIO CAMBIO POSTGRESQL 17
/** @group postgres-integration */
class ExchangeRatePrecisionUpgradeTest extends \Tests\TestCase
{
    public function test_new_installation_precision_is_verified_without_converting_data(): void
    {
        if (getenv('PRO9_POSTGRES_TESTS')!=='1') $this->markTestSkipped('Requiere PostgreSQL temporal.');
        $config=PostgresTestEnvironment::connection();config(['database.connections.precision_control'=>$config]);
        $control=DB::connection('precision_control');PostgresTestEnvironment::assertServer($control);
        $name='pro9_fiscal_test_'.bin2hex(random_bytes(6));$control->statement('CREATE DATABASE "'.$name.'" TEMPLATE template0');
        config(['database.connections.precision_test'=>array_merge($config,['database'=>$name]),'exchange_rate_precision'=>['documents'=>['exchange_rate_sale']]]);
        $db=DB::connection('precision_test');
        try {
            $db->statement('CREATE TABLE documents (id bigint PRIMARY KEY, exchange_rate_sale numeric(18,8) NOT NULL, total numeric(30,12))');
            $db->table('documents')->insert(['id'=>1,'exchange_rate_sale'=>'873.86712345','total'=>'0.123456789012']);
            $service=new ExchangeRatePrecisionUpgrade();self::assertSame([],$service->upgrade($db,true));self::assertSame([],$service->upgrade($db));
            self::assertSame('873.86712345',$db->table('documents')->value('exchange_rate_sale'));
            $db->statement('ALTER TABLE documents ALTER COLUMN exchange_rate_sale TYPE numeric(20,8)');
            try {$service->upgrade($db);self::fail('Debe rechazar una definición incompatible sin modificarla.');}
            catch (\RuntimeException $error) {self::assertStringContainsString('no se convierten',$error->getMessage());}
            self::assertSame('numeric(20,8)',\App\Database\SchemaInspector::column($db,'documents','exchange_rate_sale')->type);
            self::assertSame('0.123456789012',$db->table('documents')->value('total'));
        } finally {DB::purge('precision_test');$control->statement('DROP DATABASE "'.$name.'" WITH (FORCE)');DB::purge('precision_control');}
    }
}
// ######### FIN CAMBIO POSTGRESQL 17
