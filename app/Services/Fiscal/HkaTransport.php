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

    public function downloadPdf(string $token, array $identity): Response
    {
        return $this->request($token, 20)->post('https://demoemisionv2.thefactoryhka.com.ve/api/DescargaArchivo', [
            'serie' => $identity['serie'],
            'tipoDocumento' => $identity['tipoDocumento'],
            'numeroDocumento' => $identity['numeroDocumento'],
            'tipoArchivo' => 'PDF',
        ]);
    }

    private function request(string $token, int $timeout)
    {
        return Http::acceptJson()->asJson()->withToken($token)->timeout($timeout)->connectTimeout(5)
            ->withOptions(['allow_redirects' => false, 'verify' => true]);
    }

    public function applyIvaRetention(string $token, array $payload): Response
    {
        return $this->request($token,20)->post('https://demoemisionv2.thefactoryhka.com.ve/api/AplicarRetencion',$payload);
    }

    public function queryIvaRetention(string $token, array $identity): Response
    {
        return $this->request($token,10)->send('GET','https://demoemisionv2.thefactoryhka.com.ve/api/AplicarRetencion',['json'=>$identity]);
    }

    public function removeIvaRetention(string $token, array $identity): Response
    {
        return $this->request($token,20)->send('DELETE','https://demoemisionv2.thefactoryhka.com.ve/api/AplicarRetencion',['json'=>$identity]);
    }
    public function applyIslrRetention(string $token, array $payload): Response
    {
        return $this->request($token,20)->post('https://demoemisionv2.thefactoryhka.com.ve/api/AplicarRetencionISLR',$payload);
    }
    public function queryIslrRetention(string $token, array $identity): Response
    {
        return $this->request($token,10)->send('GET','https://demoemisionv2.thefactoryhka.com.ve/api/AplicarRetencionISLR',['json'=>$identity]);
    }
    public function removeIslrRetention(string $token, array $identity): Response
    {
        return $this->request($token,20)->send('DELETE','https://demoemisionv2.thefactoryhka.com.ve/api/AplicarRetencionISLR',['json'=>$identity]);
    }
}
