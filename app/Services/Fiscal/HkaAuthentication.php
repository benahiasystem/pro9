<?php

namespace App\Services\Fiscal;

use App\Models\Tenant\Company;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

/** Autenticación HKA y reutilización interna del JWT. */
final class HkaAuthentication
{
    private const URLS = [
        'demo' => 'https://demoemisionv2.thefactoryhka.com.ve/api/Autenticacion',
        'production' => 'https://emisionv2.thefactoryhka.com.ve/api/Autenticacion',
    ];

    public function authenticate(string $environment, string $usuario, string $clave): array
    {
        if (!isset(self::URLS[$environment])) {
            throw ValidationException::withMessages(['fiscal_environment' => 'El ambiente fiscal de HKA no es válido.']);
        }
        try {
            $response = Http::acceptJson()->asJson()->timeout(10)->connectTimeout(5)
                ->withOptions(['allow_redirects' => false, 'verify' => true])
                ->post(self::URLS[$environment], ['usuario' => $usuario, 'clave' => $clave]);
        } catch (\Throwable $exception) {
            throw ValidationException::withMessages([
                'hka_clave' => 'No fue posible conectar con HKA. Intente de nuevo.',
            ]);
        }

        $body = $response->json();
        if (!$response->successful() || !is_array($body) || !is_string($body['token'] ?? null)
            || trim($body['token']) === '' || !is_string($body['expiracion'] ?? null)) {
            throw ValidationException::withMessages([
                'hka_clave' => 'HKA rechazó la autenticación o devolvió una respuesta incompleta.',
            ]);
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2})?$/', $body['expiracion'])) {
            throw ValidationException::withMessages([
                'hka_clave' => 'HKA devolvió una expiración inválida.',
            ]);
        }

        try {
            $expiresAt = CarbonImmutable::parse($body['expiracion'], config('app.timezone', 'America/Caracas'));
        } catch (\Throwable $exception) {
            throw ValidationException::withMessages([
                'hka_clave' => 'HKA devolvió una expiración inválida.',
            ]);
        }

        if ($expiresAt->lessThanOrEqualTo(CarbonImmutable::now()->addMinute())) {
            throw ValidationException::withMessages([
                'hka_clave' => 'El token de HKA ya expiró o está por expirar.',
            ]);
        }

        return ['token' => $body['token'], 'expires_at' => $expiresAt];
    }

    public static function credentials(Company $company): ?array
    {
        try {
            $decoded = json_decode((string) $company->fiscal_credentials, true);
        } catch (\Throwable $exception) {
            return null;
        }
        if (!is_array($decoded) || !is_string($decoded['usuario'] ?? null)
            || !is_string($decoded['clave'] ?? null) || $decoded['usuario'] === '' || $decoded['clave'] === '') {
            return null;
        }

        return ['usuario' => $decoded['usuario'], 'clave' => $decoded['clave']];
    }

    /** Entrega el JWT únicamente a futuros adaptadores internos de HKA. */
    public function tokenFor(Company $company): string
    {
        if ($company->fiscal_emission_mode !== 'digital' || !isset(self::URLS[$company->fiscal_environment])
            || !($credentials = self::credentials($company))) {
            throw ValidationException::withMessages(['fiscal_emission_mode' => 'La conexión HKA no está configurada.']);
        }

        $key = 'hka_token_' . hash_hmac('sha256',
            $company->getConnection()->getDatabaseName() . ':' . $company->id . ':' . $company->fiscal_environment . ':' . json_encode($credentials),
            (string) config('app.key'));
        $cached = Cache::get($key);
        if (is_string($cached)) {
            try {
                return Crypt::decryptString($cached);
            } catch (\Throwable $exception) {
                Cache::forget($key);
            }
        }

        $authenticated = $this->authenticate($company->fiscal_environment, $credentials['usuario'], $credentials['clave']);
        $ttl = min(12 * 60 * 60, $authenticated['expires_at']->getTimestamp() - time()) - 60;
        if ($ttl > 0) {
            Cache::put($key, Crypt::encryptString($authenticated['token']), $ttl);
        }

        return $authenticated['token'];
    }
}
