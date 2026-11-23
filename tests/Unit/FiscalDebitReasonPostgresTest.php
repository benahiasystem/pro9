<?php

namespace Tests\Unit;

use App\CoreFacturalo\Facturalo;
use App\Http\Requests\Tenant\DocumentRequest;
use App\Support\Exact\Decimal;
use Illuminate\Routing\Redirector;
use Illuminate\Routing\RouteCollection;
use Illuminate\Routing\UrlGenerator;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/** @group postgres-integration */
final class FiscalDebitReasonPostgresTest extends TestCase
{
    public function test_penalty_reason_cannot_create_a_debit_note_at_either_side_of_half_a_cent(): void
    {
        $fixture = new FiscalEmissionSchemaTest();
        (new ReflectionMethod($fixture, 'setUp'))->invoke($fixture);

        try {
            [$db, $app, $invoiceInput, $invoice] = (new ReflectionMethod($fixture, 'noteFixture'))->invoke($fixture);
            $makeNote = new ReflectionMethod($fixture, 'noteInput');
            $line = $invoice->items()->firstOrFail();
            $rows = [['affected_document_item_id' => $line->id, 'adjustment_amount' => '11.60']];
            $count = $db->table('documents')->count();

            foreach ([['0', '0'], ['0.004', '0.004'], ['0.005', '0'], ['0', '0.005']] as [$headerIva, $lineIva]) {
                $input = $makeNote->invoke($fixture, $invoiceInput, $invoice, '08', '13', $rows);
                $input['total_igv'] = $headerIva;
                $input['items'][0]['total_igv'] = $lineIva;
                $request = $this->request($app, $input);

                try {
                    $request->validateResolved();
                    (new Facturalo())->forWebDocuments()->save($request->all());
                    self::fail('El motivo de penalidad no debe crear una nota de débito.');
                } catch (ValidationException $error) {
                    $expected = $headerIva !== '0' || $lineIva !== '0'
                        ? ['note.note_debit_type_id' => ['Las penalidades son operaciones inafectas del IVA']]
                        : ['note' => ['Seleccione un motivo vigente para este tipo de nota.']];
                    self::assertSame($expected, $error->errors());
                }
                self::assertSame($count, $db->table('documents')->count());
                self::assertSame(0, $db->transactionLevel());
            }

            foreach ([['abc', '0', 'total_igv'], [[], '0', 'total_igv'], ['0', '0.0000000000001', 'items.total_igv'], ['0', '0.004', 'note.note_debit_type_id']] as [$headerIva,$lineIva,$field]) {
                $input = $makeNote->invoke($fixture, $invoiceInput, $invoice, '08', '13', $rows);
                $input['total_igv']=$headerIva; $input['items'][0]['total_igv']=$lineIva;
                try {
                    $this->request($app,$input)->validateResolved();
                    self::fail('El IVA inválido o positivo debe rechazarse.');
                } catch (ValidationException $error) { self::assertArrayHasKey($field,$error->errors()); }
                self::assertSame($count,$db->table('documents')->count());
            }

            $input = $makeNote->invoke($fixture, $invoiceInput, $invoice, '08', '02', $rows);
            $request = $this->request($app, $input);
            $request->validateResolved();
            $note = (new Facturalo())->forWebDocuments()->save($request->all())->getDocument()->fresh();
            self::assertSame('02', $note->note->note_debit_type_id);
            self::assertSame('11.6', Decimal::represent($note->exactValue('total')));
            self::assertSame($count + 1, $db->table('documents')->count());
        } finally {
            (new ReflectionMethod($fixture, 'tearDown'))->invoke($fixture);
        }
    }

    private function request(\Illuminate\Foundation\Application $app, array $input): DocumentRequest
    {
        $request = DocumentRequest::create('http://fiscal-schema.example.test/documents', 'POST', $input);
        $request->setContainer($app);
        $request->setRedirector(new Redirector(new UrlGenerator(new RouteCollection(), $request)));

        return $request;
    }
}
