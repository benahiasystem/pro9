<?php

namespace Tests\Unit;

use App\Services\Fiscal\HkaTransport;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HkaTransportTest extends TestCase
{
    public function test_serialization_never_exposes_internal_payload_or_response(): void
    {
        $emission = new \App\Models\Tenant\DocumentEmission();
        $emission->setRawAttributes(['status' => 'prepared',
            'payload' => '{"private":"payload"}', 'response' => '{"private":"provider-context"}']);
        self::assertArrayNotHasKey('payload', $emission->toArray());
        self::assertArrayNotHasKey('response', $emission->toArray());
        $document = new \App\Models\Tenant\Document();
        $document->setRelation('emission', $emission);
        self::assertArrayNotHasKey('emission', $document->toArray());
    }

    public function test_mutation_and_query_use_demo_bearer_tls_and_bounded_timeouts_without_redirects(): void
    {
        $requests = [];
        Http::preventStrayRequests();
        Http::fake(function ($request, $options) use (&$requests) {
            $requests[] = [$request->url(), $request->data(), $options['timeout']];
            self::assertSame(['Bearer test-token'], $request->header('Authorization'));
            self::assertTrue($options['verify']);
            self::assertFalse($options['allow_redirects']);
            self::assertSame(5, $options['connect_timeout']);
            return Http::response(['codigo' => '500'], 200);
        });
        $transport = new HkaTransport();
        $transport->emission('test-token', ['documentoElectronico' => ['test' => 'frozen']]);
        $transport->status('test-token', ['tipoDocumento' => '01', 'transaccionId' => 'operation-1']);
        self::assertSame([
            ['https://demoemisionv2.thefactoryhka.com.ve/api/Emision', ['documentoElectronico' => ['test' => 'frozen']], 20],
            ['https://demoemisionv2.thefactoryhka.com.ve/api/EstadoDocumento', ['tipoDocumento' => '01', 'transaccionId' => 'operation-1'], 10],
        ], $requests);
        Http::assertSentCount(2);
    }
}
