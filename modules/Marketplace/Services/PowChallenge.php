<?php

namespace Modules\Marketplace\Services;

use Illuminate\Support\Facades\Cache;

/**
 * Desafío proof-of-work estilo ALTCHA, autohospedado y sin dependencias.
 *
 * El objetivo NO es hacer imposible el abuso sino encarecerlo: para generar un
 * enlace de contacto, el navegador tiene que quemar unos cientos de ms de CPU.
 * Un vecino ni lo nota (la UI muestra «Resolviendo desafío…» un instante); un
 * script que quiera recorrer el directorio paga ese costo por CADA contacto,
 * encima del throttle. Cero registro externo y cero datos a terceros — a
 * diferencia de Turnstile, todo queda en este servidor.
 *
 * Protocolo (idéntico en espíritu a ALTCHA):
 *  1. El servidor elige un número secreto aleatorio en [0, MAX_NUMBER] y emite
 *     { salt, challenge = sha256(salt . número), signature = HMAC(challenge) }.
 *  2. El navegador brute-forcea el número probando sha256(salt . n) hasta dar
 *     con el challenge.
 *  3. El servidor verifica en O(1): la firma (el desafío es suyo), la
 *     expiración (va embebida en el salt) y que sha256(salt . número) coincida.
 *     Cada desafío se acepta UNA sola vez (candado en cache).
 */
class PowChallenge
{
    /** Techo del brute-force del navegador: ~centenas de ms en un móvil medio. */
    public const MAX_NUMBER = 120000;

    /** Un desafío emitido caduca a los 10 minutos. */
    private const TTL_SECONDS = 600;

    public static function issue(): array
    {
        $expires = time() + self::TTL_SECONDS;
        // La expiración viaja DENTRO del salt: así queda cubierta por la firma
        // y no hace falta guardar nada en servidor al emitir.
        $salt = bin2hex(random_bytes(12)) . '.' . $expires;
        $number = random_int(0, self::MAX_NUMBER);

        $challenge = hash('sha256', $salt . $number);

        return [
            'algorithm' => 'SHA-256',
            'salt' => $salt,
            'challenge' => $challenge,
            'signature' => self::sign($challenge),
            'maxnumber' => self::MAX_NUMBER,
        ];
    }

    /**
     * Valida la solución {salt, challenge, number, signature} del cliente.
     */
    public static function verify(?array $solution): bool
    {
        $salt = (string) ($solution['salt'] ?? '');
        $challenge = (string) ($solution['challenge'] ?? '');
        $signature = (string) ($solution['signature'] ?? '');
        $number = $solution['number'] ?? null;

        if ($salt === '' || $challenge === '' || $signature === '' || ! is_numeric($number)) {
            return false;
        }

        // 1. La firma: el desafío lo emitió este servidor, no el cliente.
        if (! hash_equals(self::sign($challenge), $signature)) {
            return false;
        }

        // 2. La expiración, embebida en el salt.
        $expires = (int) (explode('.', $salt)[1] ?? 0);
        if ($expires < time()) {
            return false;
        }

        // 3. El trabajo: el número encontrado reproduce el challenge.
        if (! hash_equals($challenge, hash('sha256', $salt . (int) $number))) {
            return false;
        }

        // 4. Un solo uso: Cache::add es atómico; el segundo intento con el
        //    mismo desafío no consigue el candado y se rechaza.
        return Cache::add('mkt:pow:' . $challenge, 1, self::TTL_SECONDS);
    }

    private static function sign(string $challenge): string
    {
        return hash_hmac('sha256', $challenge, (string) config('app.key'));
    }
}
