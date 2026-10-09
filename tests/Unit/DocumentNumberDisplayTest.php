<?php

namespace Tests\Unit;

use App\Services\Fiscal\FiscalIdentity;
use PHPUnit\Framework\TestCase;

class DocumentNumberDisplayTest extends TestCase
{
    /** @dataProvider numbers */
    public function test_numbers_have_at_least_eight_digits_without_changing_placeholders($number, string $expected): void
    {
        self::assertSame($expected, FiscalIdentity::displayNumber($number));
        self::assertSame($expected, FiscalIdentity::numberFull('', $number));
        self::assertSame('FF-01-'.$expected, FiscalIdentity::numberFull('FF-01', $number));
    }

    public static function numbers(): array
    {
        return [[25, '00000025'], [0, '00000000'], ['00000025', '00000025'],
            [12345678, '12345678'], ['123456789', '123456789'],
            [null, ''], ['', ''], ['#', '#'], ['Pendiente', 'Pendiente'], ['25A', '25A']];
    }

    public function test_internal_documents_share_display_and_keep_raw_numbers(): void
    {
        foreach ([\App\Models\Tenant\Document::class, \App\Models\Tenant\Dispatch::class,
            \App\Models\Tenant\SaleNote::class, \App\Models\Tenant\Retention::class,
            \App\Models\Tenant\Perception::class, \App\Models\Tenant\PurchaseSettlement::class,
            \Modules\Inventory\Models\Guide::class, \Modules\Inventory\Models\InventoryTransfer::class] as $class) {
            $document = new $class();
            $document->setRawAttributes(['series' => 'FF-01', 'number' => 25, 'control_number' => '00-00000033']);
            self::assertSame('FF-01-00000025', $document->number_full, $class);
            self::assertSame(25, $document->getAttributes()['number']);
            self::assertSame('00-00000033', $document->getAttributes()['control_number']);
        }
        foreach ([\App\Models\Tenant\Quotation::class, \Modules\Purchase\Models\PurchaseOrder::class,
            \Modules\Sale\Models\SaleOpportunity::class, \Modules\Sale\Models\Contract::class,
            \Modules\Inventory\Models\Devolution::class, \Modules\Order\Models\OrderNote::class,
            \Modules\Order\Models\OrderForm::class, \Modules\Sale\Models\TechnicalService::class,
            \Modules\Payment\Models\PaymentLink::class] as $class) {
            $document = new $class();
            $document->setRawAttributes(['id' => 25, 'prefix' => 'DOC']);
            self::assertStringEndsWith('-00000025', $document->number_full, $class);
            self::assertSame(25, $document->id);
        }
    }

    public function test_report_references_preserve_series_and_accept_padded_input(): void
    {
        self::assertSame('FF-AB-01-00000025', FiscalIdentity::displayReference('FF-AB-01-25'));
        self::assertSame('00000025', FiscalIdentity::displayReference('25'));
        self::assertSame('FF01-00000025', FiscalIdentity::displayReference('FF01-00000025'));
        self::assertSame('#', FiscalIdentity::displayReference('#'));
    }

    public function test_external_supplier_numbers_are_preserved(): void
    {
        foreach ([\App\Models\Tenant\Purchase::class, \Modules\Purchase\Models\FixedAssetPurchase::class] as $class) {
            $document = new $class();
            $document->setRawAttributes(['series' => 'PROV', 'number' => '25A']);
            self::assertSame('PROV-25A', $document->number_full);
            $document->setRawAttributes(['series' => 'PROV', 'number' => '25']);
            self::assertSame('PROV-25', $document->number_full);
        }
    }
}
