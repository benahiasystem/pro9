<?php
// ######## INICIO TASAS OCHO DECIMALES ########
namespace Tests\Unit;
use App\Services\ExchangeRates\ExchangeRatePrecisionUpgrade;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ExchangeRatePrecisionUpgradeTest extends TestCase
{
    public function test_upgrade_preserves_values_defaults_and_other_columns(): void
    {
        if (getenv('PRO9_FISCAL_MYSQL_TESTS') !== '1') $this->markTestSkipped('Requiere MySQL temporal.');
        $env = \Dotenv\Dotenv::parse(file_get_contents(dirname(__DIR__, 2).'/.env'));
        $config = ['driver' => 'mysql', 'host' => getenv('DB_HOST') ?: $env['DB_HOST'], 'port' => $env['DB_PORT'] ?? 3306,
            'username' => $env['DB_USERNAME'], 'password' => $env['DB_PASSWORD'], 'database' => 'information_schema',
            'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'prefix' => ''];
        config()->set('database.connections.precision_control', $config);
        $control = DB::connection('precision_control');
        $name = 'pro9_precision_test_'.bin2hex(random_bytes(6));
        $control->statement('CREATE DATABASE `'.$name.'`');
        $config['database'] = $name;
        config()->set('database.connections.precision_test', $config);
        $db = DB::connection('precision_test');
        try {
            $db->statement("CREATE TABLE documents (id INT PRIMARY KEY, exchange_rate_sale DECIMAL(13,3) NOT NULL DEFAULT '1.000', total DECIMAL(12,2) NOT NULL)");
            $db->statement('CREATE TABLE exchange_rates (date DATE PRIMARY KEY, purchase DECIMAL(13,3) NULL DEFAULT NULL, sale DECIMAL(13,3) NOT NULL, purchase_original DECIMAL(13,3) NOT NULL, sale_original DECIMAL(13,3) NOT NULL)');
            $db->table('documents')->insert(['id' => 1, 'exchange_rate_sale' => '873.867', 'total' => '120.50']);
            $service = new ExchangeRatePrecisionUpgrade();
            self::assertCount(5, $service->upgrade($db, true));
            self::assertSame('decimal(13,3)', $db->selectOne("SHOW COLUMNS FROM documents WHERE Field = 'exchange_rate_sale'")->Type);
            self::assertCount(5, $service->upgrade($db));
            self::assertSame([], $service->upgrade($db));
            $column = $db->selectOne("SHOW COLUMNS FROM documents WHERE Field = 'exchange_rate_sale'");
            self::assertSame('decimal(18,8)', $column->Type);
            self::assertSame('1.00000000', $column->Default);
            self::assertSame('873.86700000', $db->table('documents')->value('exchange_rate_sale'));
            self::assertSame('120.50', $db->table('documents')->value('total'));
            self::assertSame('decimal(12,2)', $db->selectOne("SHOW COLUMNS FROM documents WHERE Field = 'total'")->Type);
            $db->table('documents')->insert(['id' => 2, 'exchange_rate_sale' => '873.86712345', 'total' => '1000.00']);
            self::assertSame('873.86712345', $db->table('documents')->where('id', 2)->value('exchange_rate_sale'));
            self::assertSame('YES', $db->selectOne("SHOW COLUMNS FROM exchange_rates WHERE Field = 'purchase'")->Null);
        } finally {
            DB::purge('precision_test');
            $control->statement('DROP DATABASE `'.$name.'`');
            DB::purge('precision_control');
        }
    }
}
// ######## FIN TASAS OCHO DECIMALES ########
