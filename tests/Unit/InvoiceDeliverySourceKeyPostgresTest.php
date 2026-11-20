<?php

namespace Tests\Unit;

use App\CoreFacturalo\Facturalo;
use App\CoreFacturalo\Requests\Inputs\DispatchInput;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/** @group postgres-integration */
final class InvoiceDeliverySourceKeyPostgresTest extends TestCase
{
    public function test_delivery_order_conversion_validates_the_source_key_before_lookup(): void
    {
        $fixture = new FiscalEmissionSchemaTest();
        (new ReflectionMethod($fixture, 'setUp'))->invoke($fixture);
        try {
            [$db, , $orderInput, $invoice] = (new ReflectionMethod($fixture, 'dispatchFixture'))->invoke($fixture);
            $db->table('companies')->update(['fiscal_emission_mode' => 'free_form']);
            $order = (new Facturalo())->save(DispatchInput::set($orderInput))->getDocument();
            $key = (string) $order->id;
            $inventory = $db->table('item_warehouse')->get()->toArray();

            foreach (['9223372036854775808', $key.'x', '0'.$key, $key.'.0', [$key]] as $invalid) {
                try {
                    (new Facturalo())->forWebDocuments()->save(array_replace($invoice, ['dispatch_id' => $invalid]));
                    self::fail('La conversión debe rechazar la clave de orden inválida.');
                } catch (ValidationException $error) {
                    self::assertArrayHasKey('dispatch_id', $error->errors());
                }
            }

            self::assertSame(0, $db->table('documents')->count());
            self::assertSame(0, $db->table('document_payments')->count());
            self::assertNull($order->fresh()->reference_document_id);
            self::assertEquals($inventory, $db->table('item_warehouse')->get()->toArray());

            $invoice['dispatch_id'] = $key;
            $invoice['payments'] = [];
            $invoice['payment_condition_id'] = '02';
            $document = (new Facturalo())->forWebDocuments()->save($invoice)->getDocument();
            self::assertSame($document->id, $order->fresh()->reference_document_id);
        } finally {
            (new ReflectionMethod($fixture, 'tearDown'))->invoke($fixture);
        }
    }
}
