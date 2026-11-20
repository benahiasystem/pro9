<?php

namespace Tests\Unit;

use App\CoreFacturalo\Facturalo;
use Illuminate\Support\Str;
use Illuminate\View\ViewServiceProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Symfony\Component\Process\Process;

/** @group postgres-integration */
final class InvoiceDistributedDiscountPdfPostgresTest extends TestCase
{
    public function test_the_a4_themes_reconcile_distributed_discount_headers_to_the_saved_invoice(): void
    {
        $fixture = new FiscalEmissionSchemaTest();
        (new ReflectionMethod($fixture, 'setUp'))->invoke($fixture);

        try {
            [$db, $app, $input] = (new ReflectionMethod($fixture, 'bootHkaFixture'))->invoke($fixture, false);
            $db->table('companies')->update(['fiscal_emission_mode' => 'free_form']);
            $db->table('configurations')->update(['auto_send_hka' => false]);
            $input['payment_condition_id'] = '02';
            $input['payments'] = [];
            $input['items'][0]['quantity'] = '1';
            $input['items'][0]['item']['description'] = 'PARTIDA DISTRIBUIDA';
            $input['items'][0]['name_product_pdf'] = 'PARTIDA DISTRIBUIDA';

            $compiled = sys_get_temp_dir().'/'.$db->getDatabaseName().'/distributed-discount-a4';
            mkdir($compiled, 0777, true);
            config([
                'view.compiled' => $compiled,
                'view.paths' => [base_path('resources/views')],
                'filesystems.disks.public' => ['driver' => 'local', 'root' => sys_get_temp_dir()],
            ]);
            $app->register(ViewServiceProvider::class);

            foreach ([
                ['00', '8671/75', '29/75', '115.61', '0.39'],
                ['01', '347/3', '1/3', '115.67', '0.33'],
            ] as [$type, $net, $discount, $netText, $discountText]) {
                $input['external_id'] = (string) Str::uuid();
                $input['items'][0]['discounts'] = [[
                    'discount_type_id' => $type,
                    'description' => 'Descuento distribuido de prueba',
                    'factor' => '0',
                    'amount' => '0.333333333333',
                    'amount_exact' => ['n' => '1', 'd' => '3'],
                    'is_amount' => true,
                    'from_global_distribution' => true,
                ]];
                $invoice = (new Facturalo())->forWebDocuments()->save($input)->getDocument()->fresh();
                self::assertTrue($invoice->exactValue('total')->isEqualTo($net));
                self::assertTrue($invoice->exactDiscountWithIgv()->isEqualTo($discount));
                $line = $invoice->items()->sole();
                self::assertTrue($line->exactValue('total')->isEqualTo($net));

                foreach (['default', 'modern-2027', 'Plantilla_personalizable'] as $theme) {
                    $db->table('establishments')->where('id', $invoice->establishment_id)->update(['template_pdf' => $theme]);
                    $pdf = (new Facturalo())->createPdf($invoice->fresh(), 'invoice', 'a4', 'string');
                    self::assertStringStartsWith('%PDF-', $pdf, $theme);
                    $extract = new Process(['pdftotext', '-layout', '-', '-']);
                    $extract->setInput($pdf)->mustRun();
                    $text = $extract->getOutput();

                    // The default theme shows the net line for the type-01 distributed discount.
                    $lineTotal = $type === '01' && $theme === 'default' ? $netText : '116.00';
                    self::assertMatchesRegularExpression('/PARTIDA DISTRIBUIDA[^\r\n]*\h'.preg_quote($lineTotal, '/').'\h*$/m', $text, $theme);
                    foreach (['SUMA DE IMPORTES' => '116.00', 'DESCUENTO TOTAL' => $discountText, 'TOTAL A PAGAR' => $netText] as $label => $amount) {
                        self::assertMatchesRegularExpression('/(?:^|\h{2,})'.$label.'\b[^\r\n]*\h'.preg_quote($amount, '/').'\h*$/m', $text, $theme);
                    }
                }
            }
        } finally {
            (new ReflectionMethod($fixture, 'tearDown'))->invoke($fixture);
        }
    }
}
