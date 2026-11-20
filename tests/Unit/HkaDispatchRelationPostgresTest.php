<?php

namespace Tests\Unit;

use App\CoreFacturalo\Facturalo;
use App\CoreFacturalo\Requests\Inputs\DispatchInput;
use App\Services\Fiscal\HkaDispatchRelations;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/** @group postgres-integration */
final class HkaDispatchRelationPostgresTest extends TestCase
{
    public function test_relation_waits_for_reliable_evidence_and_recovers_after_a_timeout(): void
    {
        $fixture = new FiscalEmissionSchemaTest();
        (new ReflectionMethod($fixture, 'setUp'))->invoke($fixture);
        try {
            [$db, , $raw, $invoiceInput] = (new ReflectionMethod($fixture, 'dispatchFixture'))->invoke($fixture);
            \Tests\Support\PostgresTestEnvironment::assertServer($db);
            $order = (new Facturalo())->save(DispatchInput::set($raw))->getDocument()->fresh();
            $invoice = (new Facturalo())->forWebDocuments()->save($invoiceInput)->getDocument()->fresh();
            self::assertSame('confirmed', $order->emission->status);
            self::assertSame('confirmed', $invoice->emission->status);

            $service = new HkaDispatchRelations();
            self::assertSame('waiting', $service->sync($order)['status']);
            $db->transaction(fn () => HkaDispatchRelations::attach($invoice, ['dispatch_id' => $order->id]));
            self::assertSame($invoice->id, $order->fresh()->reference_document_id);

            $keys = array_flip(['serie', 'tipoDocumento', 'numeroDocumento']);
            $identity = static fn ($record) => array_intersect_key(
                $record->emission->payload['documentoElectronico']['encabezado']['identificacionDocumento'],
                $keys
            );
            $source = $identity($order);
            $target = $identity($invoice);
            $nextQuery = 'incomplete';
            $calls = [];
            Http::swap(new Factory());
            Http::preventStrayRequests();
            Http::fake(function (Request $request) use ($db, $source, $target, &$nextQuery, &$calls) {
                self::assertSame(0, $db->transactionLevel());
                if (str_ends_with($request->url(), '/Autenticacion')) {
                    return Http::response(['token' => 'token-sintetico', 'expiracion' => now()->addHour()->toIso8601String()]);
                }
                self::assertStringEndsWith('/Documentos/Relacionar', parse_url($request->url(), PHP_URL_PATH));
                $calls[] = $request->method();
                if ($request->method() === 'POST') {
                    self::assertEquals($source, array_intersect_key($request->data(), $source));
                    self::assertEquals([$target], $request->data()['relacionados']);
                    $nextQuery = 'present';
                    throw new ConnectionException('Respuesta sintética interrumpida después de registrar la relación.');
                }
                return match ($nextQuery) {
                    'incomplete' => Http::response(['codigo' => '200', 'relacionados' => [['serie' => $target['serie']]]]),
                    'absent' => Http::response(['codigo' => '404', 'mensaje' => 'Relación no encontrada',
                        'validaciones' => ['Relación no encontrada en nuestra base de datos.']]),
                    'present' => Http::response(['codigo' => '200', 'relacionados' => [$target]]),
                };
            });

            self::assertSame('uncertain', $service->sync($order)['status']);
            self::assertSame(['GET'], $calls, 'Una fila incompleta en HKA no acredita ausencia.');
            self::assertSame('uncertain', $order->fresh()->emission->response['relation']['status']);

            $nextQuery = 'absent';
            self::assertSame('uncertain', $service->sync($order)['status']);
            self::assertSame(['GET', 'GET', 'POST'], $calls);
            self::assertSame('uncertain', $order->fresh()->emission->response['relation']['status']);
            self::assertSame('confirmed', $service->sync($order)['status']);
            self::assertSame('confirmed', $service->sync($order)['status']);
            self::assertSame(['GET', 'GET', 'POST', 'GET'], $calls);
            self::assertSame('confirmed', $order->fresh()->emission->response['relation']['status']);
        } finally {
            (new ReflectionMethod($fixture, 'tearDown'))->invoke($fixture);
        }
    }
}
