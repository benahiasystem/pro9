<?php

namespace Tests\Unit;

use App\CoreFacturalo\Facturalo;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/** @group postgres-integration */
final class InvoiceQuantityButtonsPostgresTest extends TestCase
{
    public function test_invoice_line_uses_the_adjusted_fraction_on_create_and_edit(): void
    {
        $fixture = new PrepaymentConsumersPostgresTest();
        (new ReflectionMethod($fixture, 'setUp'))->invoke($fixture);
        try {
            $input = (new ReflectionMethod($fixture, 'input'))->invoke($fixture, '1', false);
            $row = $input['items'][0];
            $row['unit_price'] = '3';
            $row['quantity'] = '1.333333333333';
            $row['quantity_input'] = ['amount' => '1', 'unit_price' => ['n' => '3', 'd' => '4']];
            $input['items'] = [$row];
            $input['payments'] = [];
            $invoice = (new Facturalo())->forWebDocuments()->save($input)->getDocument();
            $line = $invoice->items()->firstOrFail();
            self::assertTrue($line->exactValue('quantity')->isEqualTo('4/3'));
            self::assertTrue($line->exactValue('total')->isEqualTo('4'));
            self::assertEquals($row['quantity_input'], $line->calculation_snapshot['inputs']['quantity_input']);

            $row['record_id'] = $line->id;
            $row['quantity'] = '0.333333333333';
            $row['quantity_input'] = ['amount' => '1', 'unit_price' => ['n' => '3', 'd' => '1']];
            $edit = array_replace($input, ['id' => $invoice->id, 'series' => $invoice->series, 'number' => $invoice->number,
                'received_retentions' => [], 'guarantee_fund' => null, 'items' => [$row]]);
            (new Facturalo())->forWebDocuments()->update($edit, $invoice->id);
            $saved = $invoice->fresh()->items()->firstOrFail();
            self::assertTrue($saved->exactValue('quantity')->isEqualTo('1/3'));
            self::assertTrue($saved->exactValue('total')->isEqualTo('1'));
            self::assertEquals($row['quantity_input'], $saved->calculation_snapshot['inputs']['quantity_input']);
        } finally {
            (new ReflectionMethod($fixture, 'tearDown'))->invoke($fixture);
        }
    }
}
