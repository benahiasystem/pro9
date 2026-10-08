<?php

namespace Tests\Unit;

use App\Services\Fiscal\HkaResponse;
use PHPUnit\Framework\TestCase;

class HkaResponseTest extends TestCase
{
    private function identity(): array
    {
        return ['serie' => '', 'tipoDocumento' => '01', 'numeroDocumento' => '21', 'transaccionId' => 'demo-operation'];
    }

    private function success(): array
    {
        return ['codigo' => '200', 'validaciones' => [], 'resultado' => $this->identity() + ['numeroControl' => '00-00000021']];
    }

    public function test_confirmation_requires_business_success_and_matching_identity(): void
    {
        self::assertSame('confirmed', HkaResponse::interpret(200, $this->success(), $this->identity())['status']);
        foreach (['tipoDocumento' => '02', 'numeroDocumento' => '22', 'serie' => 'OTRA', 'transaccionId' => 'other', 'numeroControl' => ''] as $field => $value) {
            $body = $this->success(); $body['resultado'][$field] = $value;
            self::assertSame('uncertain', HkaResponse::interpret(200, $body, $this->identity())['status']);
        }
        $body = $this->success(); unset($body['resultado']['serie']);
        self::assertSame('uncertain', HkaResponse::interpret(200, $body, $this->identity())['status']);
    }

    public function test_duplicate_minimum_registration_and_incomplete_responses_are_uncertain(): void
    {
        foreach ([null, [], ['codigo' => '200'], ['codigo' => '200', 'validaciones' => 'invalid'],
            ['codigo' => '201'], ['codigo' => '210'], ['codigo' => '204'], ['codigo' => '500']] as $body) {
            self::assertSame('uncertain', HkaResponse::interpret(200, $body, $this->identity())['status']);
        }
        foreach ([401, 500, 302] as $http) self::assertSame('uncertain', HkaResponse::interpret($http, $this->success(), $this->identity())['status']);
        $body = $this->success(); $body['validaciones'] = ['bad field'];
        self::assertSame('uncertain', HkaResponse::interpret(200, $body, $this->identity())['status']);
    }

    public function test_rejection_and_diagnostics_do_not_persist_raw_provider_text(): void
    {
        foreach (['202', '203', '205', '400', '401'] as $code) {
            $result = HkaResponse::interpret(200, ['codigo' => $code, 'mensaje' => 'token private-value', 'validaciones' => ['password private-value']], $this->identity());
            self::assertSame('rejected', $result['status']);
            self::assertStringNotContainsString('private-value', json_encode($result));
        }
        $result = HkaResponse::interpret(200, ['codigo' => 'private-value'], $this->identity());
        self::assertNull($result['code']);
    }

    public function test_only_the_verified_absence_envelope_allows_a_retry(): void
    {
        $body = ['codigo' => '203', 'mensaje' => 'Consulta no procesada', 'estado' => null,
            'validaciones' => ['Documento no encontrado en nuestra base de datos']];
        self::assertTrue(HkaResponse::interpret(200, $body, $this->identity(), true)['retry_allowed']);
        self::assertFalse(HkaResponse::interpret(200, $body, $this->identity())['retry_allowed']);
        $body['validaciones'][] = 'another error';
        self::assertFalse(HkaResponse::interpret(200, $body, $this->identity(), true)['retry_allowed']);
    }

    public function test_real_demo_state_dates_and_range_rejection_are_understood(): void
    {
        $body = ['codigo' => '200', 'estado' => $this->identity() + ['numeroControl' => '00-00000021',
            'estadoDocumento' => 'Enviada', 'fechaAsignacion' => '29/09/2026', 'horaAsignacion' => '07:10:53 PM']];
        $result = HkaResponse::interpret(200, $body, $this->identity(), true);
        self::assertSame('confirmed', $result['status']);
        self::assertSame('2026-09-29 19:10:53', $result['assigned_at']);
        $result = HkaResponse::interpret(200, ['codigo' => '203', 'validaciones' => ['No posee rango de numeración disponible']], $this->identity());
        self::assertStringContainsString('rango de numeración', $result['diagnostic']);
    }

    public function test_query_requires_transaction_identity_and_a_known_processed_state(): void
    {
        $body = ['codigo' => '200', 'estado' => $this->identity() + ['numeroControl' => '00-21', 'estadoDocumento' => 'Procesado']];
        self::assertSame('00-00000021', HkaResponse::interpret(200, $body, $this->identity(), true)['control_number']);
        $body['estado']['estadoDocumento'] = 'unknown';
        self::assertSame('uncertain', HkaResponse::interpret(200, $body, $this->identity(), true)['status']);
        $body['estado']['estadoDocumento'] = 'Procesado'; unset($body['estado']['transaccionId']);
        self::assertSame('uncertain', HkaResponse::interpret(200, $body, $this->identity(), true)['status']);
    }
}
