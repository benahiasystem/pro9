<?php

namespace Modules\ExtraServices\Services;
use Modules\ExtraServices\Models\ExtraServices;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class ApidocsService
{

    /**
     ** URL base de la API
     */
    protected  $hostname;

    /**
     * Reseller ID
     */
    protected  $resellerId;

    /**
     * Secret key para firma HMAC
     */
    protected  $secret;

    /**
     * URL base de la API
     */
    protected  $baseUrl;

    // Constructor para inicializar las propiedades del servicio
    public function __construct()
    {
        $this->hostname = $this->getMainHostname();
        $this->resellerId = $this->getResellerId();
        $this->secret = config('app.url_base');
        $this->baseUrl = config('app.url_apidocs');
    }

    /**
     * Obtener el hostname principal considerando subdominios
     * @return string Retorna el hostname principal del sistema
     */
    protected function getMainHostname(): string
    {
        $prefix = env('PREFIX_URL', null);
        $baseUrl = env('APP_URL_BASE');

        return !empty($prefix) ? $prefix . '.' . $baseUrl : $baseUrl;
    }

    /**
     * Obtener el Reseller ID desde el dominio principal
     * Extrae solo el nombre del dominio sin extensiones ni subdominios de prefijo
     * @return string Retorna el Reseller ID
     */
    protected function getResellerId(): string
    {

        $hostname = $this->hostname;

        $prefix = env('PREFIX_URL', null);
        if (!empty($prefix) && strpos($hostname, $prefix . '.') === 0) {
            $hostname = substr($hostname, strlen($prefix) + 1);
        }

        $parts = explode('.', $hostname);

        if (count($parts) === 1) {
            return $parts[0];
        }

        return $parts[0];
    }

    /**
     * Generar firma HMAC SHA256
     * @param string $method Método HTTP (GET, POST, etc.)
     * @param string $path Ruta de la API
     * @param int $timestamp Marca de tiempo actual
     * @return string Retorna la firma HMAC codificada en base64
     */
    protected function generateSignature(string $method, string $path, int $timestamp): string
    {
        Log::info('Generating signature for request', [
            'method' => $method,
            'path' => $path,
            'timestamp' => $timestamp,
            'hostname' => $this->hostname,
            'resellerId' => $this->resellerId,
            'secret' => $this->secret,
            'baseurl' => $this->baseUrl,
        ]);

        $payload = implode("\n", [
            $method,
            $path,
            $timestamp,
            $this->hostname,
        ]);

        return base64_encode(
            hash_hmac('sha256', $payload, $this->secret, true)
        );
    }

    /**
     * Preparar headers para la request
     * @param string $method Método HTTP (GET, POST, etc.)
     * @param string $path Ruta de la API
     * @return array Retorna un array con los headers necesarios para la autenticación
     */
    protected function prepareHeaders(string $method, string $path): array
    {
        $timestamp = time();
        $signature = $this->generateSignature($method, $path, $timestamp);

        return [
            'X-Reseller-Id' => $this->resellerId,
            'X-Timestamp' => $timestamp,
            'X-Signature' => $signature,
            'X-Domain' => $this->hostname,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];
    }

    /**
     * Realizar consulta HTTP a la API
     * @param string $method Método HTTP (GET, POST, etc.)
     * @param string $path Ruta de la API
     * @return array Retorna un array con la respuesta de la API o un mensaje de error
     */
    protected function makeRequest(string $method, string $path): array
    {
        try {

            $config = ExtraServices::first();

            if (!$config || !$config->isActiveApidocs) {
                throw new Exception('El servicio de consultas no está activo');
            }

            $headers = $this->prepareHeaders($method, $path);
            Log::info('Request headers', [
                'X-Reseller-Id' => $this->resellerId,
                'X-Timestamp' => $headers['X-Timestamp'],
                'X-Signature' => $headers['X-Signature'],
                'X-Domain' => $this->hostname,
            ]);
            $url = $this->baseUrl . $path;

            $response = Http::withoutVerifying()
                ->withHeaders($headers)
                ->timeout(30)
                ->get($url);

            $responseData = $response->json();

            //Verificar si la respuesta fue exitosa
            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $responseData,
                ];
            } else {
                return [
                    'success' => false,
                    'message' => $responseData['message'] ?? 'Error en la consulta',
                ];
            }

        } catch (Exception $e) {
            Log::error('ApiDocsService Error: ' . $e->getMessage(), [
                'path' => $path,
                'method' => $method,
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Consultar RUC
     */
    public function queryRuc(string $ruc): array
    {
        $ruc = trim($ruc);

        if (strlen($ruc) !== 11) {
            return [
                'success' => false,
                'message' => 'El RUC debe tener 11 dígitos',
            ];
        }

        return $this->makeRequest('GET', '/api/ruc/' . $ruc);
    }

    /**
     * Consultar DNI
     */
    public function queryDni(string $dni): array
    {
        $dni = trim($dni);

        if (strlen($dni) !== 8) {
            return [
                'success' => false,
                'message' => 'El DNI debe tener 8 dígitos',
            ];
        }

        return $this->makeRequest('GET', '/api/dni/' . $dni);
    }

    /**
     * Consultar Carnet de Extranjería
     */
    public function queryCe(string $ce): array
    {
        $ce = trim($ce);

        if (strlen($ce) < 9 || strlen($ce) > 12) {
            return [
                'success' => false,
                'message' => 'El CE debe tener entre 9 y 12 caracteres',
            ];
        }

        return $this->makeRequest('GET', '/api/ce/' . $ce);
    }

    /**
     * Consultar Placa vehicular
     */
    public function queryPlaca(string $placa): array
    {
        $placa = strtoupper(trim($placa));

        if (strlen($placa) < 6 || strlen($placa) > 7) {
            return [
                'success' => false,
                'message' => 'La placa debe tener entre 6 y 7 caracteres',
            ];
        }

        return $this->makeRequest('GET', '/api/placa/' . $placa);
    }

    /**
     * Consultar Licencia de conducir
     */
    public function queryLicencia(string $licencia): array
    {
        $licencia = trim($licencia);

        if (strlen($licencia) < 8 || strlen($licencia) > 9) {
            return [
                'success' => false,
                'message' => 'La licencia debe tener entre 8 y 9 caracteres',
            ];
        }

        return $this->makeRequest('GET', '/api/licencia/' . $licencia);
    }

    /**
     * Verifica si el servicio 'apidocs' está activo en el sistema.
     * @return bool Retorna true si está activo, false si no lo está.
     */
    public function isActiveService(): bool
    {
        try {
            $resellerId = $this->getResellerId();
            $url = $this->baseUrl . '/admin/resellers/' . $resellerId . '/exists';

            $response = Http::withoutVerifying()
                ->timeout(15)
                ->get($url);

            if (!$response->successful()) {
                return false;
            }

            return (bool) ($response->json('exists') ?? false);
        } catch (Exception $e) {
            Log::error('ApiDocsService isActiveService Error: ' . $e->getMessage(), [
                'resellerId' => $this->resellerId ?? null,
            ]);

            return false;
        }
    }
}