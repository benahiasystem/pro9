<?php
// ######## INICIO API BCV ########
namespace App\Services\ExchangeRates;

use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpKernel\Exception\HttpException;

class BcvClient
{
    public function dollarRate(): string
    {
        $token = config('services.bcv.token');
        $url = config('services.bcv.url');
        if (!is_string($token) || !preg_match('/^[a-f0-9]{64}$/D', $token) || !is_string($url) || !$url) {
            throw new HttpException(503, 'La conexión con API BCV no está configurada.');
        }
        try {
            $response = Http::acceptJson()->withToken($token)
                ->timeout(config('services.bcv.timeout', 15))
                ->connectTimeout(config('services.bcv.connect_timeout', 5))
                ->withOptions(['allow_redirects' => false, 'verify' => true])
                ->get(rtrim($url, '/') . '/');
            $rate = $response->json('dolar');
            if (!$response->successful() || !is_string($rate)) {
                throw new \RuntimeException('Respuesta inválida.');
            }
            $rate = ExchangeRateMath::rate($rate);
            return $rate;
        } catch (\Throwable $exception) {
            // No conservar la excepción HTTP: puede contener el encabezado de autorización.
            throw new HttpException(503, 'No se pudo consultar una tasa válida del BCV.');
        }
    }
}
// ######## FIN API BCV ########
