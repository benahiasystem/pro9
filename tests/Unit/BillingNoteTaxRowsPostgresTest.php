<?php

namespace Tests\Unit;

use App\CoreFacturalo\Facturalo;
use App\Models\Tenant\Document;
use Illuminate\Http\Client\Factory;
use Illuminate\Routing\UrlGenerator;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Str;
use Illuminate\View\ViewServiceProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Symfony\Component\Process\Process;

/** @group postgres-integration */
final class BillingNoteTaxRowsPostgresTest extends TestCase
{
    public function test_ordinary_note_pdfs_print_the_applicable_tax_base_in_each_active_theme(): void
    {
        $fixture = new FiscalEmissionSchemaTest();
        (new ReflectionMethod($fixture, 'setUp'))->invoke($fixture);

        try {
            [$db, $app, $input] = (new ReflectionMethod($fixture, 'bootHkaFixture'))->invoke($fixture, false);
            $app->instance('url', new UrlGenerator($app['router']->getRoutes(), $app['request'], public_path()));
            Facade::clearResolvedInstance('url');
            $views = sys_get_temp_dir().'/'.$db->getDatabaseName().'/note-tax-views';
            mkdir($views, 0700, true);
            config([
                'view.compiled' => $views,
                'view.paths' => [resource_path('views')],
                'filesystems.disks.public' => ['driver' => 'local', 'root' => sys_get_temp_dir()],
            ]);
            $app->register(ViewServiceProvider::class);
            $app->make(Factory::class)->fake();
            $db->table('companies')->update(['fiscal_emission_mode' => 'free_form']);
            $db->table('configurations')->update(['auto_send_hka' => false]);

            $input['payments'] = [];
            $input['payment_condition_id'] = '02';
            $input['items'][1] = array_replace($input['items'][0], ['affectation_igv_type_id' => '20']);
            $invoice = (new Facturalo())->forWebDocuments()->save($input)->getDocument()->fresh();
            $makeNote = new ReflectionMethod($fixture, 'noteInput');
            $taxed = $invoice->items()->where('affectation_igv_type_id', '10')->firstOrFail();
            $exempt = $invoice->items()->where('affectation_igv_type_id', '20')->firstOrFail();
            $credit = (new Facturalo())->forWebDocuments()->save($makeNote->invoke($fixture, $input, $invoice, '07', '09', [
                ['affected_document_item_id' => $taxed->id, 'adjustment_amount' => '11.60'],
            ]))->getDocument()->fresh();
            $debit = (new Facturalo())->forWebDocuments()->save($makeNote->invoke($fixture, $input, $invoice, '08', '02', [
                ['affected_document_item_id' => $exempt->id, 'adjustment_amount' => '7.25'],
            ]))->getDocument()->fresh();

            $controller=app(\App\Http\Controllers\Tenant\DownloadController::class);
            foreach ([[$credit,'TOTAL NOTA DE CRÉDITO'],[$debit,'TOTAL NOTA DE DÉBITO']] as [$note,$label]) {
                $response=$controller->toTicket('document',$note->id,'a5');
                self::assertStringContainsString($label,mb_strtoupper($response->getContent(),'UTF-8'));
                self::assertStringContainsString('no-store',$response->headers->get('Cache-Control'));
                $ticket=$controller->toTicket('document',$note->id,'ticket');
                self::assertStringContainsString('NOTA DE',mb_strtoupper($ticket->getContent(),'UTF-8'));
            }
            $operator=auth()->user();
            $same=new \App\Models\Tenant\User(['type'=>'seller','establishment_id'=>$invoice->establishment_id]);
            auth()->setUser($same);
            self::assertSame(200,$controller->toTicket('document',$credit->id,'ticket')->getStatusCode());
            $other=new \App\Models\Tenant\User(['type'=>'seller','establishment_id'=>$invoice->establishment_id+100]);
            auth()->setUser($other);
            try {
                $controller->toTicket('document',$credit->id,'a5');
                self::fail('Una sucursal distinta no debe imprimir el comprobante.');
            } catch (\Symfony\Component\HttpKernel\Exception\HttpException $error) {
                self::assertSame(403,$error->getStatusCode());
            } finally { auth()->setUser($operator); }

            $tiny = $input;
            $tiny['external_id'] = (string) Str::uuid();
            $tiny['items'] = [array_replace($input['items'][1], [
                'quantity' => '0.000000000001',
                'quantity_input' => ['amount' => '0.000000000001', 'unit_price' => '3'],
                'unit_price' => '0.1',
            ])];
            $tinyInvoice = (new Facturalo())->forWebDocuments()->save($tiny)->getDocument()->fresh();
            $tinyNote = (new Facturalo())->forWebDocuments()->save(
                $makeNote->invoke($fixture, $tiny, $tinyInvoice, '07', '01', [])
            )->getDocument()->fresh();

            self::assertTrue($credit->exactValue('total_taxed')->isEqualTo('10'));
            self::assertTrue($credit->exactValue('total_exonerated')->isZero());
            self::assertTrue($debit->exactValue('total_taxed')->isZero());
            self::assertTrue($debit->exactValue('total_exonerated')->isEqualTo('7.25'));
            self::assertTrue($tinyNote->exactValue('total_exonerated')->isEqualTo('1/30000000000000'));
            self::assertSame('0', $tinyNote->total_exonerated);

            foreach ([$credit, $debit, $tinyNote] as $note) {
                self::assertTrue($note->exactValue('total_exportation')->isZero());
                self::assertTrue($note->exactValue('total_prepayment')->isZero());
                self::assertTrue($note->exactDiscountWithIgv()->isZero());
            }
            $totals = [
                ['credit', $credit, '10.00', null],
                ['debit', $debit, null, '7.25'],
                ['credit', $tinyNote, null, null],
            ];
            foreach (['default', 'Plantilla_personalizable', 'modern-2027', 'marca_de_agua'] as $theme) {
                $db->table('establishments')->update(['template_pdf' => $theme, 'template_ticket_pdf' => $theme]);
                foreach (['a4', 'a5', 'ticket'] as $format) {
                    foreach ($totals as [$type, $note, $taxedAmount, $exemptAmount]) {
                        $this->assertTaxRows($note->fresh(), $type, $format, $theme, $taxedAmount, $exemptAmount);
                    }
                }
            }
        } finally {
            (new ReflectionMethod($fixture, 'tearDown'))->invoke($fixture);
        }
    }

    private function assertTaxRows(
        Document $document,
        string $type,
        string $format,
        string $theme,
        ?string $taxedAmount,
        ?string $exemptAmount
    ): void {
        $context = "$theme/$format/$type/".$document->id;
        $pdf = (new Facturalo())->createPdf($document, $type, $format, 'string');
        self::assertStringStartsWith('%PDF-', $pdf, $context);
        $extract = new Process(['pdftotext', '-layout', '-', '-']);
        $extract->setInput($pdf)->mustRun();
        self::assertSame(1, substr_count($extract->getOutput(), "\f"), $context);
        $text = mb_strtoupper(preg_replace('/\s+/u', ' ', $extract->getOutput()), 'UTF-8');
        foreach (['OP. GRAVADAS' => $taxedAmount, 'OP. EXONERADAS' => $exemptAmount] as $label => $amount) {
            $row = preg_quote($label, '/').'\s*:';
            if ($amount === null) {
                self::assertDoesNotMatchRegularExpression("/$row/u", $text, $context);
            } else {
                $total = preg_quote($amount, '/');
                self::assertMatchesRegularExpression("/{$row}\s*BS\.?\s*{$total}\b/u", $text, $context);
            }
        }
        self::assertDoesNotMatchRegularExpression('/(?:OP\. EXPORTACI[ÓO]N|DESCUENTO TOTAL|ANTICIPO)\s*:/u', $text, $context);
    }
}
