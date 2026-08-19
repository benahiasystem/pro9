<?php

namespace App\Services\System;

use App\Exceptions\System\RifLookupException;
use App\Support\System\Rif;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class RifLookupService
{
    // ########## INICIO CAMBIO RIF SUPER ADMIN
    public function isAvailable(): bool
    {
        $url = trim((string) config('services.super_admin_rif_lookup.url'));
        $token = trim((string) config('services.super_admin_rif_lookup.token'));
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        if (!config('services.super_admin_rif_lookup.enabled', false)
            || $url === ''
            || $token === ''
            || substr_count($url, '{rif}') !== 1
            || !filter_var(str_replace('{rif}', 'J123456789', $url), FILTER_VALIDATE_URL)) {
            return false;
        }

        return app()->environment(['local', 'testing'])
            ? in_array($scheme, ['http', 'https'], true)
            : $scheme === 'https';
    }

    public function lookup(string $rif): array
    {
        $normalizedRif = Rif::normalize($rif);

        if (!Rif::isValid($normalizedRif)) {
            throw new RifLookupException('El RIF debe contener un prefijo V, E, J, P o G y nueve dígitos.', 422);
        }

        if (!$this->isAvailable()) {
            throw new RifLookupException('La consulta de RIF no está disponible. Puede continuar con la carga manual.', 503);
        }

        $url = str_replace(
            '{rif}',
            rawurlencode($normalizedRif),
            (string) config('services.super_admin_rif_lookup.url')
        );
        $timeout = max(1, min(30, (int) config('services.super_admin_rif_lookup.timeout', 10)));

        try {
            $response = Http::acceptJson()
                ->withToken((string) config('services.super_admin_rif_lookup.token'))
                ->connectTimeout($timeout)
                ->timeout($timeout)
                ->withOptions(['allow_redirects' => false])
                ->get($url);

            if ($response->status() === 404) {
                throw new RifLookupException('No se encontró un contribuyente con el RIF indicado.', 404);
            }

            if (in_array($response->status(), [401, 403], true)) {
                $this->logProviderStatus($response->status());
                throw new RifLookupException('No fue posible consultar el RIF. Puede continuar con la carga manual.', 502);
            }

            if ($response->status() === 429) {
                $this->logProviderStatus(429);
                throw new RifLookupException('El servicio de consulta está temporalmente ocupado. Puede continuar con la carga manual.', 503);
            }

            if (!$response->successful()) {
                $this->logProviderStatus($response->status());
                throw new RifLookupException('No fue posible consultar el RIF. Puede continuar con la carga manual.', 502);
            }

            $payload = $response->json();
            if (!is_array($payload)) {
                throw new RifLookupException('La respuesta del servicio de RIF no es válida. Puede continuar con la carga manual.', 502);
            }

            $name = $this->firstString($payload, [
                'data.name',
                'data.razon_social',
                'data.razonSocial',
                'data.nombre_o_razon_social',
                'name',
                'razon_social',
                'razonSocial',
                'nombre_o_razon_social',
            ]);

            if ($name === null) {
                throw new RifLookupException('La respuesta del servicio de RIF no contiene una razón social válida.', 502);
            }

            return array_filter([
                'name' => $name,
                'trade_name' => $this->firstString($payload, [
                    'data.trade_name',
                    'data.nombre_comercial',
                    'data.nombreComercial',
                    'trade_name',
                    'nombre_comercial',
                    'nombreComercial',
                ]),
            ], static fn ($value): bool => $value !== null);
        } catch (RifLookupException $exception) {
            throw $exception;
        } catch (ConnectionException $exception) {
            Log::warning('Fallo de conexión con el proveedor RIF del super admin');
            throw new RifLookupException('No fue posible consultar el RIF. Puede continuar con la carga manual.', 503);
        } catch (Throwable $exception) {
            Log::error('Fallo inesperado del proveedor RIF del super admin', [
                'exception' => get_class($exception),
            ]);
            throw new RifLookupException('No fue posible consultar el RIF. Puede continuar con la carga manual.', 502);
        }
    }

    private function firstString(array $payload, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = data_get($payload, $key);
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
    }

    private function logProviderStatus(int $status): void
    {
        Log::warning('Respuesta no satisfactoria del proveedor RIF del super admin', [
            'status' => $status,
        ]);
    }
    // ######### FIN CAMBIO RIF SUPER ADMIN
}
