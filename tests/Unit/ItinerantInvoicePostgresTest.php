<?php

namespace Tests\Unit;

use App\CoreFacturalo\Facturalo;
use App\CoreFacturalo\InputRequest;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Symfony\Component\Process\Process;

/** @group postgres-integration */
final class ItinerantInvoicePostgresTest extends TestCase
{
    public function test_the_web_invoice_resolves_an_itinerant_address_from_the_territorial_catalog(): void
    {
        $fixture = new FiscalEmissionSchemaTest();
        (new ReflectionMethod($fixture, 'setUp'))->invoke($fixture);

        try {
            [$db, $app, $input] = (new ReflectionMethod($fixture, 'bootHkaFixture'))->invoke($fixture, true);
            $db->table('companies')->update(['fiscal_emission_mode' => 'free_form']);
            $db->table('configurations')->update(['auto_send_hka' => false]);
            $db->table('persons')->where('id', $input['customer_id'])->update(['email' => 'cliente@example.test']);
            $input['operation_type_id'] = '0101';
            $input['date_of_due'] = '2026-10-10';
            $input['is_itinerant'] = true;
            $input['customer_address_id'] = null;
            $input['itinerant'] = ['id' => 2, 'address' => [
                'address' => 'Avenida itinerante de prueba',
                'department_id' => '14', 'province_id' => '0229', 'district_id' => '000619',
                'country_id' => 'VE', 'telephone' => '04120000000',
            ]];
            $input['payment_condition_id'] = '02';
            $input['payments'] = [];
            $request = Request::create('/documents', 'POST', $input);
            $request->attributes->set('sab.strict_input_exceptions', true);
            $invoice = (new InputRequest())->handle($request, static function (Request $normalized) use ($db) {
                $buyer = $normalized->input('customer');
                self::assertSame('cliente@example.test', $buyer['email']);
                self::assertSame('04120000000', $buyer['telephone']);
                foreach (['country' => 'countries', 'department' => 'departments', 'province' => 'provinces', 'district' => 'districts'] as $key => $table) {
                    $id = $buyer[$key.'_id'];
                    self::assertSame($db->table($table)->where('id', $id)->value('description'), $buyer[$key]['description']);
                    self::assertNotEmpty($buyer[$key]['description']);
                }
                return (new Facturalo())->forWebDocuments()->save($normalized->all())->getDocument()->fresh();
            }, 'document', 'web');
            $snapshot = (array) $invoice->customer;
            self::assertSame('Avenida itinerante de prueba', $snapshot['address']);
            self::assertSame('000619', $snapshot['district_id']);
            self::assertSame('cliente@example.test', $snapshot['email']);

            $input['itinerant']['address']['district_id'] = '999999';
            try {
                (new InputRequest())->handle(Request::create('/documents', 'POST', $input),
                    static fn () => self::fail('La parroquia inválida llegó al guardado.'), 'document', 'web');
                self::fail('La factura requiere una parroquia existente cuando se declara su código.');
            } catch (ValidationException $error) {
                self::assertArrayHasKey('district_id', $error->errors());
            }
            self::assertSame(1, $db->table('documents')->count());
            self::assertSame(0, $db->transactionLevel());

            $db->table('persons')->where('id', $input['customer_id'])->update(['address' => 'Avenida habitual de prueba']);
            $input['external_id'] = (string) Str::uuid();
            $input['itinerant'] = ['id' => 1];
            $regularAddress = (new InputRequest())->handle(
                Request::create('/documents', 'POST', $input),
                static fn (Request $request) => (new Facturalo())->forWebDocuments()->save($request->all())->getDocument()->fresh(),
                'document',
                'web'
            );
            self::assertSame('Avenida habitual de prueba', $regularAddress->customer->address);
            self::assertSame(2, $db->table('documents')->count());

            $compiled = sys_get_temp_dir().'/'.$db->getDatabaseName().'/itinerant-a4';
            mkdir($compiled, 0777, true);
            config([
                'view.compiled' => $compiled,
                'view.paths' => [base_path('resources/views')],
                'filesystems.disks.public' => ['driver' => 'local', 'root' => sys_get_temp_dir()],
            ]);
            $app->register(\Illuminate\View\ViewServiceProvider::class);
            foreach (['default', 'modern-2027', 'Plantilla_personalizable'] as $theme) {
                $db->table('establishments')->where('id', $invoice->establishment_id)->update(['template_pdf' => $theme]);
                foreach ([
                    [$regularAddress, 'Avenida habitual de prueba', 'Avenida itinerante de prueba'],
                    [$invoice, 'Avenida itinerante de prueba', 'Avenida habitual de prueba'],
                ] as [$document, $expected, $other]) {
                    $pdf = (new Facturalo())->createPdf($document->fresh(), 'invoice', 'a4', 'string');
                    self::assertStringStartsWith('%PDF-', $pdf, $theme);
                    $extract = new Process(['pdftotext', '-layout', '-', '-']);
                    $extract->setInput($pdf)->mustRun();
                    $output = mb_strtolower($extract->getOutput());
                    self::assertStringContainsString(mb_strtolower($expected), $output, $theme);
                    self::assertStringNotContainsString(mb_strtolower($other), $output, $theme);
                }
            }
        } finally {
            (new ReflectionMethod($fixture, 'tearDown'))->invoke($fixture);
        }
    }
}
