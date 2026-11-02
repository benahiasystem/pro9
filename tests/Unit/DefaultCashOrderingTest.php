<?php

namespace Tests\Unit;

use App\Services\Payments\Classification;
use Hyn\Tenancy\Database\Connection as TenancyConnection;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\TestCase;

class DefaultCashOrderingTest extends TestCase
{
    public function test_sqlite_default_cash_uses_binary_unicode_order(): void
    {
        $previousContainer = Container::getInstance();
        $previousResolver = Model::getConnectionResolver();
        $previousDispatcher = Model::getEventDispatcher();
        try {
            $app = new Container();
            Container::setInstance($app);
            $tenancy = $this->getMockBuilder(TenancyConnection::class)->disableOriginalConstructor()->onlyMethods(['tenantName'])->getMock();
            $tenancy->method('tenantName')->willReturn('tenant');
            $app->instance(TenancyConnection::class, $tenancy);
            $db = new Manager($app);
            $db->addConnection(['driver'=>'sqlite', 'database'=>':memory:', 'prefix'=>''], 'tenant');
            $db->bootEloquent();
            $connection = $db->getConnection('tenant');
            $connection->statement('CREATE TABLE cat_currency_types (id TEXT PRIMARY KEY, active INTEGER)');
            $connection->statement('CREATE TABLE payment_method_types (id TEXT PRIMARY KEY, collection_kind TEXT, currency_type_id TEXT, is_active INTEGER)');
            $connection->table('cat_currency_types')->insert(['id'=>'VES', 'active'=>1]);
            foreach (['^', '01', '!', "\u{10000}", "\u{E000}"] as $id) {
                $connection->table('payment_method_types')->insert(['id'=>$id, 'collection_kind'=>'cash', 'currency_type_id'=>'VES', 'is_active'=>1]);
            }
            self::assertSame('!', Classification::defaultCashMethod('VES')->id);
            $connection->table('payment_method_types')->whereIn('id', ['!', '01', '^'])->delete();
            self::assertSame("\u{E000}", Classification::defaultCashMethod('VES')->id);
        } finally {
            Model::clearBootedModels();
            if ($previousResolver) Model::setConnectionResolver($previousResolver); else Model::unsetConnectionResolver();
            if ($previousDispatcher) Model::setEventDispatcher($previousDispatcher); else Model::unsetEventDispatcher();
            Container::setInstance($previousContainer);
        }
    }
}
