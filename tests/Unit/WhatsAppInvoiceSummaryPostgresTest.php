<?php

namespace Tests\Unit;

use App\CoreFacturalo\Facturalo;
use App\Models\Tenant\User;
use App\Support\Exact\Decimal;
use Modules\WhatsAppBot\Services\Facturalo\FacturaloDraftMapper;
use Modules\WhatsAppBot\Services\Tools\CreateDocumentTool;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/** @group postgres-integration */
final class WhatsAppInvoiceSummaryPostgresTest extends TestCase
{
    public function test_whatsapp_summary_uses_the_original_line_and_subtotal_before_rounding_to_cents(): void
    {
        $fixture = new FiscalEmissionSchemaTest();
        (new ReflectionMethod($fixture, 'setUp'))->invoke($fixture);

        try {
            [$db, , $input] = (new ReflectionMethod($fixture, 'bootHkaFixture'))->invoke($fixture, true);
            $db->table('companies')->update(['fiscal_emission_mode' => 'free_form']);
            $db->table('configurations')->update(['auto_send_hka' => false, 'auto_send_pdf_email' => false]);
            $item = $input['items'][0]['item_id'];
            $db->table('items')->where('id', $item)->update([
                'currency_type_id' => 'VES',
                'sale_unit_price' => '0.015',
                'has_igv' => false,
                'sale_affectation_igv_type_id' => '20',
            ]);
            $date = now('America/Caracas')->toDateString();
            $db->table('exchange_rates')->updateOrInsert(['date' => $date], [
                'date_original' => $date, 'sale' => '10', 'purchase' => '10',
                'sale_original' => '10', 'purchase_original' => '10',
            ]);
            $response = (new CreateDocumentTool())->execute([
                'document_type' => 'factura',
                'customer_id' => $input['customer_id'],
                'items' => [
                    ['item_id' => $item, 'quantity' => '0.333333333333'],
                    ['item_id' => $item, 'quantity' => '2'],
                ],
            ]);
            self::assertSame('ready_for_confirmation', $response['status'], json_encode($response, JSON_THROW_ON_ERROR));
            $draft = $response['draft'];
            self::assertSame('0.005', $draft['items'][0]['line_total']);
            self::assertSame('0.035', $draft['subtotal']);
            self::assertSame('0.03', $draft['total']);
            self::assertArrayNotHasKey('calculation_snapshot', $draft);
            self::assertTrue(Decimal::read($draft['items'][0], 'total')->isEqualTo('0.004999999999995'));
            self::assertMatchesRegularExpression('/0\.333333333333 x [^\n]+ \(Bs\. 0\.02\) = Bs\. 0\.00/u', $response['summary_text']);
            self::assertStringContainsString("Subtotal: Bs. 0.03\nIVA: Bs. 0.00\nTotal: Bs. 0.03", $response['summary_text']);

            $payload = (new FacturaloDraftMapper())->map($draft, User::findOrFail($input['user_id']));
            $invoice = (new Facturalo())->save($payload)->getDocument()->fresh();
            self::assertTrue($invoice->exactValue('total')->isEqualTo('0.034999999999995'));
            self::assertSame('0.03', $invoice->payments()->firstOrFail()->payment);

            $request = [
                'document_type' => 'factura',
                'customer_id' => $input['customer_id'],
                'items' => [['item_id' => $item, 'quantity' => '0.999999999999']],
            ];
            $catalogItem = $db->table('items')->where('id', $item);
            $catalogItem->update(['sale_affectation_igv_type_id' => '10', 'sale_unit_price' => '0.03125']);
            $taxed = (new CreateDocumentTool())->execute($request);
            self::assertSame('ready_for_confirmation', $taxed['status']);
            self::assertSame('0.005', $taxed['draft']['igv']);
            self::assertStringContainsString("IVA: Bs. 0.00\nTotal: Bs. 0.04", $taxed['summary_text']);

            $catalogItem->update(['sale_unit_price' => '0.035']);
            $discounted = (new CreateDocumentTool())->execute($request + ['discount_amount' => '0.01']);
            self::assertSame('ready_for_confirmation', $discounted['status']);
            self::assertSame('0.035', $discounted['draft']['subtotal_before_discount']);
            self::assertStringContainsString("Subtotal sin descuento: Bs. 0.03\nDescuento: -Bs. 0.01\nSubtotal: Bs. 0.02", $discounted['summary_text']);
        } finally {
            (new ReflectionMethod($fixture, 'tearDown'))->invoke($fixture);
        }
    }

    public function test_whatsapp_ves_invoice_keeps_the_catalog_fraction_and_large_price_after_confirmation(): void
    {
        $fixture = new FiscalEmissionSchemaTest();
        (new ReflectionMethod($fixture, 'setUp'))->invoke($fixture);
        try {
            [$db, , $input] = (new ReflectionMethod($fixture, 'bootHkaFixture'))->invoke($fixture, true);
            $db->table('companies')->update(['fiscal_emission_mode' => 'free_form']);
            $db->table('configurations')->update(['auto_send_hka' => false, 'auto_send_pdf_email' => false]);
            $item = $input['items'][0]['item_id'];
            $catalog = $db->table('items')->where('id', $item);
            $date = now('America/Caracas')->toDateString();
            $db->table('exchange_rates')->updateOrInsert(['date' => $date], [
                'date_original' => $date, 'sale' => '10', 'purchase' => '10',
                'sale_original' => '10', 'purchase_original' => '10',
            ]);
            foreach ([
                ['1/3', '3', '1', '1.00'],
                ['9007199254740993.01', '1', '9007199254740993.01', '9007199254740993.01'],
            ] as [$price, $quantity, $exactTotal, $payable]) {
                $catalog->update([
                    'currency_type_id' => 'VES', 'has_igv' => false, 'sale_affectation_igv_type_id' => '20',
                    'sale_unit_price' => Decimal::represent($price),
                    'calculation_snapshot' => json_encode(Decimal::snapshot(['sale_unit_price' => $price]), JSON_THROW_ON_ERROR),
                ]);
                $response = (new CreateDocumentTool())->execute([
                    'document_type' => 'factura', 'customer_id' => $input['customer_id'],
                    'items' => [['item_id' => $item, 'quantity' => $quantity]],
                ]);
                self::assertSame('ready_for_confirmation', $response['status'], json_encode($response, JSON_THROW_ON_ERROR));
                $draft = json_decode(json_encode($response['draft'], JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
                self::assertSame($payable, $draft['total']);
                self::assertTrue(Decimal::read($draft['items'][0], 'unit_price')->isEqualTo($price));
                self::assertTrue(Decimal::read($draft['items'][0], 'total')->isEqualTo($exactTotal));

                $user = User::findOrFail($input['user_id']);
                $mapper = new FacturaloDraftMapper();
                $invalid = $draft;
                $invalid['items'][0]['price_exact'] = ['n' => '9', 'd' => '1'];
                try {
                    $mapper->map($invalid, $user);
                    self::fail('El borrador aceptó una fracción que no coincide con el precio visible.');
                } catch (\Illuminate\Validation\ValidationException $error) {
                    self::assertArrayHasKey('items.price', $error->errors());
                }

                $catalog->update(['sale_unit_price' => '9', 'calculation_snapshot' => null]);
                $payload = $mapper->map($draft, $user);
                $invoice = (new Facturalo())->save($payload)->getDocument()->fresh();
                self::assertSame('VES', $invoice->currency_type_id);
                self::assertTrue($invoice->items()->sole()->exactValue('unit_price')->isEqualTo($price));
                self::assertTrue($invoice->exactValue('total')->isEqualTo($exactTotal));
                self::assertTrue($invoice->payments()->sole()->exactValue('payment')->isEqualTo($payable));
            }
        } finally {
            (new ReflectionMethod($fixture, 'tearDown'))->invoke($fixture);
        }
    }

    public function test_whatsapp_ves_invoice_requires_confirmation_again_if_iva_changes_without_changing_total(): void
    {
        $fixture = new FiscalEmissionSchemaTest();
        (new ReflectionMethod($fixture, 'setUp'))->invoke($fixture);
        try {
            [$db, , $input] = (new ReflectionMethod($fixture, 'bootHkaFixture'))->invoke($fixture, true);
            $db->table('companies')->update(['fiscal_emission_mode' => 'free_form']);
            $db->table('configurations')->update(['auto_send_hka' => false, 'auto_send_pdf_email' => false]);
            $item = $input['items'][0]['item_id'];
            $db->table('items')->where('id', $item)->update([
                'currency_type_id' => 'VES', 'has_igv' => true, 'sale_affectation_igv_type_id' => '10',
                'sale_unit_price' => '116', 'calculation_snapshot' => null,
            ]);
            $date = now('America/Caracas')->toDateString();
            $db->table('exchange_rates')->updateOrInsert(['date' => $date], [
                'date_original' => $date, 'sale' => '10', 'purchase' => '10',
                'sale_original' => '10', 'purchase_original' => '10',
            ]);
            config(['venezuela.tax.rate' => '0.16']);
            $tool = new CreateDocumentTool();
            $request = [
                'document_type' => 'factura', 'customer_id' => $input['customer_id'],
                'items' => [['item_id' => $item, 'quantity' => '1']],
            ];
            $response = $tool->execute($request);
            self::assertSame('ready_for_confirmation', $response['status']);
            $draft = $response['draft'];
            self::assertSame('116.00', $draft['total']);
            self::assertSame('100', $draft['subtotal']);
            self::assertSame('16', $draft['igv']);

            config(['venezuela.tax.rate' => '0.08']);
            $mapper = new FacturaloDraftMapper();
            $user = User::findOrFail($input['user_id']);
            try {
                $mapper->map($draft, $user);
                self::fail('El mismo total no autoriza cambiar la base imponible y el IVA confirmados.');
            } catch (\Illuminate\Validation\ValidationException $error) {
                self::assertArrayHasKey('total', $error->errors());
            }
            self::assertSame(0, $db->table('documents')->count());

            $new = $tool->execute($request)['draft'];
            self::assertSame('116.00', $new['total']);
            self::assertNotSame('16', $new['igv']);
            $invoice = (new Facturalo())->save($mapper->map($new, $user))->getDocument()->fresh();
            self::assertSame('VES', $invoice->currency_type_id);
            self::assertTrue($invoice->exactValue('total')->isEqualTo('116'));
            self::assertSame($new['igv'], $invoice->total_igv);
            self::assertSame($new['subtotal'], $invoice->total_value);
        } finally {
            (new ReflectionMethod($fixture, 'tearDown'))->invoke($fixture);
        }
    }
}
