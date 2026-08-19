<?php
namespace Modules\Payment\Traits;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

trait IzipayTrait
{

    // Izipay Perú (Lyra): test y producción comparten el mismo host; el entorno lo define la credencial.
    const URL = 'https://api.micuentaweb.pe/api-payment/V4/Charge/CreatePayment';
    const URL_GET_TRANSACTION = 'https://api.micuentaweb.pe/api-payment/V4/Transaction/Get';
    const KRYPTON_STATIC_BASE = 'https://static.micuentaweb.pe';

    /**
     * @param $credentials {username_izipay, password_izipay, publickey_izipay, sha256key_izipay}
     * @param $data
     */
    public function createPayment($credentials, $data, &$error = null) :? string
    {
        $error = null;
        $token = $this->izipayAuthorizationToken($credentials);

        if (!$token) {
            $error = 'No se encontraron las credenciales de Izipay configuradas';
            return null;
        }

        $response = Http::withHeaders([
            'Authorization' => 'Basic ' . $token,
            'Content-Type' => 'application/json',
        ])->post(self::URL, $data);

        $body = $response->json();

        // izipay responde 200 incluso cuando falla, el estado real va en status/answer
        if ($response->successful()
            && ($body['status'] ?? null) === 'SUCCESS'
            && isset($body['answer']['formToken'])) {
            return $body['answer']['formToken'];
        }

        $error = $this->izipayErrorMessage($body) ?: 'No se pudo generar el formulario de pago de Izipay';

        Log::error('Izipay createPayment error', [
            'status_code' => $response->status(),
            'body' => $response->body(),
        ]);

        return null;

    }

    /**
     * Mensaje legible a partir del bloque answer de un error de izipay
     */
    private function izipayErrorMessage($body): ?string
    {

        $code = data_get($body, 'answer.errorCode');
        $message = data_get($body, 'answer.detailedErrorMessage') ?: data_get($body, 'answer.errorMessage');

        if (!$code && !$message) return null;

        return implode(' - ', array_filter([$code, $message]));

    }

    /**
     * Token de autenticación básica, null si faltan las credenciales
     *
     * @param  array|null $credentials
     * @return string|null
     */
    private function izipayAuthorizationToken($credentials): ?string
    {

        $username = $credentials['username_izipay'] ?? null;
        $password = $credentials['password_izipay'] ?? null;

        if (!$username || !$password) return null;

        return base64_encode("{$username}:{$password}");

    }


    public function verifyHash(Request $request, $sha256key): bool
    {

        $krAnswer = str_replace('\/', '/',  $request["kr-answer"]);
        
        $calculateHash = hash_hmac("sha256", $krAnswer, $sha256key);

        return ($calculateHash == $request["kr-hash"]);

    }

    /**
     * @link https://secure.micuentaweb.pe/doc/es-PE/rest/V4.0/api/playground/Transaction/Get
     */

    public function getTransaction($credentials, $uuid, &$error = null): ?array {

        $error = null;
        $token = $this->izipayAuthorizationToken($credentials);

        if (!$token) {
            $error = 'No se encontraron las credenciales de Izipay configuradas';
            return null;
        }

        $response = Http::withHeaders([
            'Authorization' => 'Basic ' . $token,
            'Content-Type' => 'application/json',
        ])->post(self::URL_GET_TRANSACTION, [
            "uuid" => $uuid
        ]);

        if (!$response->successful()) {
            $error = 'No se pudo consultar la transacción en Izipay';
            Log::error('Izipay getTransaction error', [
                'status_code' => $response->status(),
                'body' => $response->body(),
            ]);
            return null;
        }

        $body = $response->json();

        // igual que en createPayment, un 200 puede traer status ERROR
        if (($body['status'] ?? null) !== 'SUCCESS') {
            $error = $this->izipayErrorMessage($body) ?: 'No se pudo consultar la transacción en Izipay';
            Log::error('Izipay getTransaction error', [
                'status_code' => $response->status(),
                'body' => $response->body(),
            ]);
        }

        return $body;
    }
}