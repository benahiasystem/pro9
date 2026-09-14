<?php

namespace Tests\Unit;

use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Modules\Account\Http\Controllers\AccountController;
use PHPUnit\Framework\TestCase;

// ######## INICIO NUMERACIÓN FISCAL VENEZUELA ########
class EstablishmentVenezuelaTaxTest extends TestCase
{
    /** @dataProvider configuredRates */
    public function test_account_export_uses_venezuelan_rate_without_querying_peruvian_establishment_flag(float $rate, float $expected): void
    {
        $previous = Container::getInstance();
        $app = new Container();
        $app->instance('config', new Repository(['venezuela' => ['tax' => ['rate' => $rate]]]));
        Container::setInstance($app);
        try {
            $method = new \ReflectionMethod(AccountController::class, 'getIgv');
            $method->setAccessible(true);
            $controller = (new \ReflectionClass(AccountController::class))->newInstanceWithoutConstructor();
            foreach (['2022-09-01', '2026-12-31', '2027-01-01'] as $date) {
                $this->assertSame($expected, $method->invoke($controller, $date, 999));
            }
        } finally {
            Container::setInstance($previous);
        }
    }

    public static function configuredRates(): array
    {
        return [[0.16, 16.0], [0.08, 8.0]];
    }
}
// ######## FIN NUMERACIÓN FISCAL VENEZUELA ########
