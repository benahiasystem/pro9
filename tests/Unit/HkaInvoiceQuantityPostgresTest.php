<?php

namespace Tests\Unit;

use App\CoreFacturalo\Facturalo;
use App\Services\Fiscal\HkaEmissionPreparation;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Tests\Support\PostgresTestEnvironment;

/** @group postgres-integration */
final class HkaInvoiceQuantityPostgresTest extends TestCase
{
    public function test_hka_preparation_rejects_positive_quantity_that_would_be_sent_as_zero(): void
    {
        $fixture = new FiscalEmissionSchemaTest();
        (new ReflectionMethod($fixture, 'setUp'))->invoke($fixture);
        try {
            [$db, , $input] = (new ReflectionMethod($fixture, 'bootHkaFixture'))->invoke($fixture);
            PostgresTestEnvironment::assertServer($db);
            $db->table('configurations')->update(['auto_send_hka' => false]);

            $input['items'][0]['quantity'] = '0.00001';
            $input['items'][0]['unit_price'] = '23200000';
            $input['items'][0]['quantity_input'] = ['amount' => '232', 'unit_price' => '23200000'];
            $invoice = (new Facturalo())->forWebDocuments()->save($input)->getDocument()->fresh();
            self::assertTrue($invoice->items->sole()->exactValue('quantity')->isEqualTo('1/100000'));
            self::assertSame('not_requested', $invoice->emission->status);
            $preparation = new HkaEmissionPreparation();

            try {
                $preparation->prepare($invoice);
                $quantity = $invoice->fresh()->emission->payload['documentoElectronico']['detallesItems'][0]['cantidad'];
                self::fail('La factura quedó preparada con cantidad HKA '.$quantity.'.');
            } catch (ValidationException $exception) {
                self::assertArrayHasKey('items', $exception->errors());
            }
            self::assertSame('not_requested', $invoice->fresh()->emission->status);
            self::assertNull($invoice->fresh()->emission->payload);

            $input['external_id'] = Str::uuid()->toString();
            $input['items'][0]['quantity'] = '0.0001';
            $input['items'][0]['unit_price'] = '2320000';
            $input['items'][0]['quantity_input'] = ['amount' => '232', 'unit_price' => '2320000'];
            $valid = (new Facturalo())->forWebDocuments()->save($input)->getDocument()->fresh();
            $emission = $preparation->prepare($valid);
            self::assertSame('prepared', $emission->status);
            self::assertSame('0.0001', $emission->payload['documentoElectronico']['detallesItems'][0]['cantidad']);
        } finally {
            (new ReflectionMethod($fixture, 'tearDown'))->invoke($fixture);
        }
    }
}
