<?php

namespace App\Services\Fiscal;

use App\Models\Tenant\Company;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/** DEMO only. No automatic retry of a fiscal mutation. */
final class HkaTransport
{
    public function token(Company $company): string
    {
        return app(HkaAuthentication::class)->tokenFor($company);
    }

    public function emission(string $token, array $payload): Response
    {
        return $this->request($token, 20)->post('https://demoemisionv2.thefactoryhka.com.ve/api/Emision', $payload);
    }

    public function status(string $token, array $identity): Response
    {
        return $this->request($token, 10)->post('https://demoemisionv2.thefactoryhka.com.ve/api/EstadoDocumento', [
            'tipoDocumento' => $identity['tipoDocumento'],
            'transaccionId' => $identity['transaccionId'],
        ]);
    }

    public function mail(string $token, array $payload): Response
    {
        return $this->request($token, 20)->post('https://demoemisionv2.thefactoryhka.com.ve/api/Correo/Enviar', $payload);
    }

    public function mailTracking(string $token, array $identity): Response
    {
        return $this->request($token, 10)->post('https://demoemisionv2.thefactoryhka.com.ve/api/Correo/Rastreo', $identity);
    }

    private function request(string $token, int $timeout)
    {
        return Http::acceptJson()->asJson()->withToken($token)->timeout($timeout)->connectTimeout(5)
            ->withOptions(['allow_redirects' => false, 'verify' => true]);
    }
}
