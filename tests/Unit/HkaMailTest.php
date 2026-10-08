<?php
namespace Tests\Unit;

use App\Services\Fiscal\HkaMail;
use App\Services\Fiscal\HkaTransport;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HkaMailTest extends TestCase
{
    public function test_mail_transport_is_demo_only_with_verified_tls_no_redirects_and_bounded_timeouts(): void
    {
        $seen = [];
        Http::preventStrayRequests();
        Http::fake(function ($request, $options) use (&$seen) {
            self::assertSame(['Bearer test-private-token'], $request->header('Authorization'));
            self::assertTrue($options['verify']); self::assertFalse($options['allow_redirects']);
            self::assertSame(5, $options['connect_timeout']);
            $seen[] = [$request->url(), $request->data(), $options['timeout']];
            return Http::response(['codigo' => '200']);
        });
        $identity = ['serie' => '', 'tipoDocumento' => '01', 'numeroDocumento' => '2'];
        (new HkaTransport)->mail('test-private-token', $identity + ['correos' => ['test@example.test']]);
        (new HkaTransport)->mailTracking('test-private-token', $identity);
        self::assertSame([
            ['https://demoemisionv2.thefactoryhka.com.ve/api/Correo/Enviar', $identity + ['correos' => ['test@example.test']], 20],
            ['https://demoemisionv2.thefactoryhka.com.ve/api/Correo/Rastreo', $identity, 10],
        ], $seen);
        Http::assertSentCount(2);
    }

    public function test_only_business_success_is_accepted_and_provider_text_is_not_exposed(): void
    {
        self::assertSame('accepted', HkaMail::interpret(200, ['codigo' => '200'])['status']);
        foreach ([null, [], ['codigo' => []], ['codigo' => '200', 'validaciones' => ['bad']], ['codigo' => '201'], ['codigo' => 'secret']] as $body) {
            self::assertSame('uncertain', HkaMail::interpret(200, $body)['status']);
        }
        foreach ([302, 401, 500] as $http) self::assertSame('uncertain', HkaMail::interpret($http, ['codigo' => '200'])['status']);
        $result = HkaMail::interpret(200, ['codigo' => '203', 'mensaje' => 'secret-token', 'validaciones' => ['private-password']]);
        self::assertSame('rejected', $result['status']);
        self::assertStringNotContainsString('secret-token', json_encode($result));
        self::assertStringNotContainsString('private-password', json_encode($result));
    }

    public function test_tracking_must_match_recipient_new_message_id_and_attempt_date(): void
    {
        $start = strtotime('2026-10-07T12:00:00-04:00');
        $entry = ['recipients' => ['test@example.test'], 'baseline' => ['old-id'], 'started_at' => $start];
        $valid = ['messageId' => 'new-id', 'correo' => 'TEST@example.test', 'status' => 'delivered', 'fecha' => '2026-10-07T12:00:01-04:00'];
        $rows = [$valid, array_replace($valid, ['correo' => 'other@example.test']), array_replace($valid, ['messageId' => 'old-id']),
            array_replace($valid, ['fecha' => '2026-10-07T11:59:59-04:00']), array_replace($valid, ['fecha' => '07/10/2026'])];
        $result = HkaMail::tracking($rows, $entry);
        self::assertCount(1, $result);
        self::assertSame('Entregado', $result[0]['description']);
        self::assertSame('test@example.test', $result[0]['recipient']);
        self::assertSame([], HkaMail::tracking($rows, array_replace($entry, ['baseline' => null])));
        $valid['status'] = 'Mensaje de Correo Electrónico entregado exitosamente.';
        self::assertSame('Entregado', HkaMail::tracking([$valid], array_replace($entry, ['baseline' => null, 'status' => 'accepted']))[0]['description']);
        $valid['status'] = 'private-token';
        self::assertStringNotContainsString('private-token', json_encode(HkaMail::tracking([$valid], $entry)));
    }

    public function test_email_request_normalizes_and_validates_each_recipient(): void
    {
        $request = \App\Http\Requests\Tenant\DocumentEmailRequest::create('/documents/email', 'POST', [
            'id' => 13, 'customer_email' => ' TEST@example.test ; other@example.test,TEST@example.test', 'request_id' => 'bad-id']);
        (new \ReflectionMethod($request, 'prepareForValidation'))->invoke($request);
        self::assertSame(['test@example.test', 'other@example.test'], $request->input('recipients'));
        $validator = app('validator')->make($request->all(), $request->rules());
        self::assertTrue($validator->fails()); self::assertTrue($validator->errors()->has('request_id'));
        $request->merge(['request_id' => (string) \Illuminate\Support\Str::uuid(), 'recipients' => ['bad-address']]);
        self::assertTrue(app('validator')->make($request->all(), $request->rules())->fails());
    }
}
