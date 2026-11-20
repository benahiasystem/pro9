<?php

namespace Tests\Unit;

use App\CoreFacturalo\{Facturalo, Template};
use App\Models\Tenant\Company;
use App\Support\Exact\Decimal;
use Illuminate\Support\Str;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;
use Tests\Support\PostgresTestEnvironment;

/** @group postgres-integration */
final class BillingPdfDiscountPostgresTest extends TestCase
{
    public function test_invoice_formats_display_a_positive_discount_below_column_precision(): void
    {
        $fixture = new class extends FiscalEmissionSchemaTest {
            public function start(): void { parent::setUp(); }
            public function stop(): void { parent::tearDown(); }
        };
        $fixture->start();
        try {
            [$db, $app, $input] = (new \ReflectionMethod($fixture, 'bootHkaFixture'))->invoke($fixture, true);
            PostgresTestEnvironment::assertServer($db);
            $app->instance('url', new \Illuminate\Routing\UrlGenerator($app['router']->getRoutes(), $app['request'], public_path()));
            \Illuminate\Support\Facades\Facade::clearResolvedInstance('url');
            $views = sys_get_temp_dir().'/'.$db->getDatabaseName().'/invoice-discount-views';
            mkdir($views, 0700, true);
            config(['view.compiled' => $views, 'view.paths' => [resource_path('views')],
                'filesystems.disks.public' => ['driver' => 'local', 'root' => sys_get_temp_dir()]]);
            $app->register(\Illuminate\View\ViewServiceProvider::class);
            $db->table('companies')->update(['fiscal_emission_mode' => 'free_form']);
            $db->table('configurations')->update(['auto_send_hka' => false]);
            $app->make(\Illuminate\Http\Client\Factory::class)->fake();

            $input['customer']['identity_document_type'] = ['description' => 'Venezolano'];
            $input['currency_type_id'] = 'VES';
            $input['payment_condition_id'] = '02';
            $input['payments'] = [];
            $input['items'][0] = array_replace($input['items'][0], [
                'quantity' => '1', 'unit_price' => '10', 'affectation_igv_type_id' => '20',
                'discounts' => [['discount_type_id' => '00', 'description' => 'Descuento', 'is_amount' => true, 'amount' => '0',
                    'amount_exact' => ['n' => '1', 'd' => '3000000000000']]],
            ]);

            $invoice = (new Facturalo())->forWebDocuments()->save($input)->getDocument()->fresh();
            self::assertTrue($invoice->exactDiscountWithIgv()->isEqualTo('1/3000000000000'));
            self::assertSame('0', $invoice->total_discount_with_igv);
            self::assertSame('0.00', Decimal::format($invoice->exactDiscountWithIgv(), 2));

            $input['external_id'] = (string) Str::uuid();
            $input['items'][0]['discounts'] = [];
            $withoutDiscount = (new Facturalo())->forWebDocuments()->save($input)->getDocument()->fresh();
            self::assertTrue($withoutDiscount->exactDiscountWithIgv()->isZero());

            $preview = $invoice->replicate();
            $preview->setRelations($invoice->getRelations());
            $company = Company::firstOrFail();
            $template = new Template();
            $missing = [];
            foreach ([
                'default' => ['a4', 'a5', 'ticket_58'],
                'modern-2027' => ['a4', 'ticket', 'ticket_58'],
                'Plantilla_personalizable' => ['a4', 'ticket', 'ticket_58'],
                'marca_de_agua' => ['ticket', 'ticket_50', 'ticket_58'],
                'nrus' => ['ticket', 'ticket_58'],
            ] as $design => $formats) {
                foreach ($formats as $format) {
                    foreach (['guardada' => $invoice, 'vista previa' => $preview] as $context => $document) {
                        $html = $template->pdf($design, 'invoice', $company, $document, $format, ['is_preview' => $context === 'vista previa']);
                        if (!self::hasDiscountRow($html)) $missing[] = "$design $format $context";
                    }
                    $plain = $template->pdf($design, 'invoice', $company, $withoutDiscount, $format);
                    self::assertFalse(self::hasDiscountRow($plain), "$design $format sin descuento");
                }
            }
            self::assertSame([], $missing, 'El descuento positivo debe activar su fila en todos los formatos probados.');

            $run = getenv('PRO9_POSTGRES_RUN_ID');
            self::assertMatchesRegularExpression('/^pro9-pgtest-[a-f0-9]{12}$/D', $run);
            $directory = base_path('informes/evidencias/postgresql_17_implementacion/resultados/billing-discount-'.$run);
            self::assertTrue(mkdir($directory, 0755, true));
            foreach (['a4', 'a5'] as $format) {
                $path = "$directory/invoice-$format.pdf";
                $bytes = (new Facturalo())->createPdf($invoice->fresh(), 'invoice', $format, 'string');
                self::assertSame(strlen($bytes), file_put_contents($path, $bytes));
                $text = (new Process(['pdftotext', '-layout', $path, '-']))->mustRun()->getOutput();
                self::assertMatchesRegularExpression('/DESCUENTO TOTAL[^\n]*0\.00/ui', $text, $format);
            }
        } finally {
            $fixture->stop();
        }
    }

    private static function hasDiscountRow(string $html): bool
    {
        $text = preg_replace('~</t[dh]>~i', "\n", $html);
        return preg_match('/^\s*Descuento(?:\s+total)?\s*:/imu', strip_tags($text)) === 1;
    }
}
