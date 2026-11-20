<?php

namespace Tests\Unit;

use App\CoreFacturalo\Facturalo;
use App\Http\Controllers\Tenant\DocumentPaymentController;
use App\Http\Requests\Tenant\DocumentPaymentRequest;
use App\Models\Tenant\Document;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Modules\MobileApp\Http\Requests\Api\ReceivedPaymentClaimRequest;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Tests\Support\PostgresTestEnvironment;

/** @group postgres-integration */
final class InvoicePaymentKeyPostgresTest extends TestCase
{
    private FiscalEmissionSchemaTest $fixture;

    protected function setUp(): void
    {
        $this->fixture = new FiscalEmissionSchemaTest();
        (new ReflectionMethod($this->fixture, 'setUp'))->invoke($this->fixture);
    }

    protected function tearDown(): void
    {
        try {
            (new ReflectionMethod($this->fixture, 'tearDown'))->invoke($this->fixture);
        } finally {
            parent::tearDown();
        }
    }

    public function test_payment_endpoints_reject_invalid_or_missing_route_keys(): void
    {
        [$db, $invoice] = $this->invoice();
        $controller = new DocumentPaymentController();
        foreach ([
            [$controller, 'document'],
            [$controller, 'records'],
            static fn ($id) => $controller->destroy($id, Request::create('/', 'DELETE')),
        ] as $endpoint) {
            foreach (['9223372036854775808', 'abc', '-1', '0'.$invoice->id, '9223372036854775807'] as $id) {
                try {
                    $endpoint($id);
                    self::fail('La consulta debe rechazar una clave inválida o un registro inexistente.');
                } catch (HttpExceptionInterface $error) {
                    self::assertSame(404, $error->getStatusCode());
                } catch (ModelNotFoundException $error) {
                    self::assertSame('9223372036854775807', $id);
                }
            }
        }
        self::assertSame($invoice->number_full, $controller->document((string) $invoice->id)['number_full']);
        self::assertCount(0, $controller->records((string) $invoice->id)->collection);
        self::assertSame(0, $db->table('document_payments')->count());
    }

    public function test_payment_requests_validate_bigint_keys_before_exists(): void
    {
        [$db, $invoice] = $this->invoice();
        validator()->setPresenceVerifier(new \Illuminate\Validation\DatabasePresenceVerifier(app('db')));
        $payload = [
            'payment' => '0.01', 'payment_method_type_id' => '01',
            'payment_destination_id' => 'cash', 'date_of_payment' => $invoice->date_of_issue->toDateString(),
        ];
        foreach ([new DocumentPaymentRequest(), new ReceivedPaymentClaimRequest()] as $form) {
            foreach (['9223372036854775808', 'abc', '+' . $invoice->id, -1, [$invoice->id]] as $id) {
                $check = validator($payload + ['document_id' => $id], $form->rules());
                self::assertArrayHasKey('document_id', $check->errors()->toArray());
            }
            $valid = validator($payload + ['document_id' => (string) $invoice->id], $form->rules())->validate();
            self::assertSame((string) $invoice->id, $valid['document_id']);
        }
        self::assertSame(0, $db->table('document_payments')->count());
    }

    public function test_direct_payment_call_validates_the_document_id(): void
    {
        [$db] = $this->invoice();
        $controller = new DocumentPaymentController();
        try {
            $controller->store(DocumentPaymentRequest::create('/document_payments', 'POST', [
                'document_id' => '9223372036854775808',
            ]));
            self::fail('La llamada directa debe validar la clave antes de consultar.');
        } catch (ValidationException $error) {
            self::assertArrayHasKey('document_id', $error->errors());
        }
        self::assertSame(0, $db->table('document_payments')->count());
    }

    /** @return array{0: \Illuminate\Database\Connection, 1: Document} */
    private function invoice(): array
    {
        [$db, , $input] = (new ReflectionMethod($this->fixture, 'bootHkaFixture'))->invoke($this->fixture);
        PostgresTestEnvironment::assertServer($db);
        $db->table('configurations')->update(['auto_send_hka' => false]);
        $db->select("SELECT setval(pg_get_serial_sequence('documents', 'id'), 9007199254740992)");
        $input['payment_condition_id'] = '02';
        $input['payments'] = [];
        $invoice = (new Facturalo())->forWebDocuments()->save($input)->getDocument()->fresh();
        self::assertSame('9007199254740993', (string) $invoice->id);
        return [$db, $invoice];
    }
}
