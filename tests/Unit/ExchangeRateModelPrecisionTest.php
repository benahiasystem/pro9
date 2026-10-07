<?php
// ######## INICIO TASAS OCHO DECIMALES ########
namespace Tests\Unit;

use App\Models\Tenant\Document;
use App\Models\Tenant\RetentionDocument;
use App\Services\ExchangeRates\ExchangeRateMath as Rate;
use Modules\Expense\Models\BankLoan;
use Modules\Sale\Models\TechnicalService;
use Tests\TestCase;

class ExchangeRateModelPrecisionTest extends TestCase
{
    public function test_models_and_typed_accessors_keep_the_complete_rate(): void
    {
        foreach (['873.86700000', '873.86712345'] as $rate) {
            $document = new Document(['exchange_rate_sale' => $rate]);
            self::assertSame($rate, $document->toArray()['exchange_rate_sale']);
            foreach ([new BankLoan(), new TechnicalService()] as $model) {
                $model->setExchangeRateSale($rate);
                self::assertSame($rate, $model->getExchangeRateSale());
                self::assertSame($rate, $model->toArray()['exchange_rate_sale']);
            }
            $retention = new RetentionDocument(['exchange_rate' => ['date' => '2026-10-07', 'sale' => $rate, 'purchase' => $rate]]);
            self::assertSame($rate, $retention->exchange_rate->sale);
            self::assertSame($rate, json_decode($retention->getAttributes()['exchange_rate'], true)['purchase']);
            self::assertArrayNotHasKey('exchange_rate', $retention->getCasts());
        }
    }

    public function test_aggregate_conversion_rounds_only_the_final_total(): void
    {
        $line = Rate::rational('0.01')->multipliedBy('873.86712345');
        self::assertSame('34.95', Rate::finalAmount($line->multipliedBy(4)));
        self::assertSame('34.96', Rate::finalAmount(Rate::rational(Rate::finalAmount($line))->multipliedBy(4)));
    }
}
// ######## FIN TASAS OCHO DECIMALES ########
