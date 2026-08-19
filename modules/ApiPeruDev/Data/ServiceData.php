<?php

namespace Modules\ApiPeruDev\Data;

use App\Models\Tenant\Company;
use App\Models\Tenant\ExchangeRate;
use GuzzleHttp\Client;
use App\Models\System\Configuration as SystemConfiguration;
use App\Models\Tenant\Configuration as TenantConfig;
use App\Models\System\TrackApiPeruServices as SystemTrackApiPeruService;
use App\Models\Tenant\TrackApiPeruServices as TenantTrackApiPeruService;
use Illuminate\Support\Facades\URL;
use Modules\ExtraServices\Models\ExtraServices;
use Modules\ExtraServices\Services\ApidocsService;
use Modules\ExtraServices\Helpers\ApidocsHelper;


class ServiceData
{
    protected $client;
    /**
     * @var TenantTrackApiPeruService|SystemConfiguration
     */
    protected $trackApi;
    /**
     * @var SystemTrackApiPeruService|TenantTrackApiPeruService
     */
    protected $configuration;
    /** @var Company */
    protected $company;
    protected $parameters;

    public function __construct()
    {
        $prefix = env('PREFIX_URL', null);
        $prefix = !empty($prefix) ? $prefix . "." : '';
        $app_url = $prefix . config('configuration.app_url_base');
        // $app_url = $prefix. env('APP_URL_BASE');
        $url = $_SERVER['HTTP_HOST'] ?? null;
        $company = null;
        // Desde admin
        $configuration = SystemConfiguration::query()->first();
        $trackApi = new SystemTrackApiPeruService();

        if ($url !== $app_url) {
            // desde cliente
            $configuration = TenantConfig::query()->first();
            $trackApi = new TenantTrackApiPeruService();
            $company = Company::first();
            if ($configuration->UseCustomApiPeruToken() == false) {
                $configuration = SystemConfiguration::query()->first();
                $trackApi = new SystemTrackApiPeruService();
            }
        }

        $url = $configuration->url_apiruc = !'' ? $configuration->url_apiruc : config('configuration.api_service_url');
        $token = $configuration->token_apiruc = !'' ? $configuration->token_apiruc : config('configuration.api_service_token');
        $this->configuration = $configuration;
        $this->trackApi = $trackApi;
        $this->company = $company;


        $this->client = new Client(['base_uri' => $url]);
        $this->parameters = [
            'http_errors' => false,
            'connect_timeout' => 10,
            'verify' => false,
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Accept' => 'application/json',
            ],
        ];
    }

    /**
     * 1 => sunat/dni
     * 2 => validacion_multiple_cpe
     * 3 => CPE
     * 4 => tipo_de_cambio
     * 5 => printer_ticket
     *
     * @param int $service
     */
    public function saveService($service = 0, $response = [])
    {

        if (isset($response['message']) &&
            strpos($response['message'], 'Ha superado la cantidad de consultas mensuales') !== false) {
            // Si se ha superado la cantidad, no hace nada.
            return $this;

        }
        $number = null;
        if (!empty($this->company)) {
            $number = $this->company->number;
        }
        $this->trackApi->setService($number, $service);
        $this->trackApi->push();
        return $this;

    }

    protected function shouldUseExtraService()
    {
        return ApidocsHelper::canUseApidocs();
    }

    /**
     * Tipos de consulta soportados por apidocs y el metodo que los resuelve.
     *
     * @return array
     */
    protected function apidocsTypes()
    {
        return [
            'ruc' => 'queryRuc',
            'dni' => 'queryDni',
            'ce' => 'queryCe',
            'placa' => 'queryPlaca',
            'licencia' => 'queryLicencia',
        ];
    }

    /**
     * Tipos que solo existen en apidocs. No tienen equivalente en el servicio
     * tradicional, por lo que no deben caer al fallback.
     *
     * @return array
     */
    protected function apidocsOnlyTypes()
    {
        return ['ce', 'placa', 'licencia'];
    }


    /**
     * Transformar respuesta de SystemExtraServices al formato esperado por el sistema
     *
     * Maneja dos fuentes de datos:
     * - "beta": API compatible con apiperudev (estructura estándar)
     * - "alpha": API Factiliza (estructura específica)
     */
    protected function transformExtraServiceResponse($response, $type)
    {
        if (!$response['success']) {
            return $response;
        }

        \Log::info('transformExtraServiceResponse', ['response' => $response, 'type' => $type]);

        // Extraer la información desde el nivel de anidación correcto ($response['data']['data'])
        $data = $response['data']['data'] ?? $response['data'] ?? [];
        $meta = $response['data']['meta'] ?? $response['meta'] ?? [];
        $source = $meta['source'] ?? 'unknown';
        $res_data = [];
        
        if ($type === 'dni') {
            $ubigeo = $data['ubigeo'] ?? [];
            $department_id = $ubigeo[0] ?? null;
            $province_id = $ubigeo[1] ?? null;
            $district_id = $ubigeo[2] ?? null;

            $res_data = [
                'name' => $data['nombre_completo'] ?? $data['name'] ?? '',
                'trade_name' => '',
                'location_id' => [
                    $department_id,
                    $province_id,
                    $district_id
                ],
                'address' => $data['direccion'] ?? $data['address'] ?? '',
                'department_id' => $department_id,
                'province_id' => $province_id,
                'district_id' => $district_id,
                'condition' => '',
                'state' => '',
            ];
        }

        if ($type === 'ruc') {
            // Determinar si es agente de retención
            $is_agent_retention = false;
            if (isset($data['es_agente_de_retencion'])) {
                $is_agent_retention = ($data['es_agente_de_retencion'] === 'SI');
            }

            $ubigeo = $data['ubigeo'] ?? [];

            $res_data = [
                'name' => $data['nombre_o_razon_social'] ?? '',
                'trade_name' => $data['nombre_comercial'] ?? '',
                'address' => $data['direccion_completa'] ?? $data['direccion'] ?? '',
                'location_id' => $ubigeo,
                'condition' => $data['condicion'] ?? '',
                'state' => $data['estado'] ?? '',
                'is_agent_retention' => $is_agent_retention,
            ];
        }

        if ($type === 'ce') {
            $res_data = [
                'name' => $data['nombre_completo'] ?? $data['nombre_o_razon_social'] ?? $data['name'] ?? '',
                'trade_name' => '',
                'address' => $data['direccion_completa'] ?? $data['direccion'] ?? $data['address'] ?? '',
                'location_id' => $data['ubigeo'] ?? [],
                'condition' => '',
                'state' => '',
            ];
        }

        if ($type === 'placa') {
            $res_data = $this->transformPlaca($data);
        }

        if ($type === 'licencia') {
            $res_data = $this->transformLicencia($data);
        }

        $response['data'] = $res_data;
        // Agregar fuente original en la respuesta
        $response['source'] = $source === 'beta' ? 'apiperu.dev' : $source;

        \Log::info('transformExtraServiceResponse - Final', ['response' => $response]);

        return $response;
    }

    /**
     * Devuelve el primer valor no vacio encontrado entre varios alias de llave.
     * Los proveedores de apidocs no comparten un mismo nombrado, por eso se
     * consultan varias variantes antes de rendirse.
     *
     * @param array $data
     * @param array $keys
     * @param mixed $default
     * @return mixed
     */
    protected function firstOf($data, array $keys, $default = '')
    {
        foreach ($keys as $key) {
            if (!isset($data[$key]) || $data[$key] === '' || $data[$key] === null) {
                continue;
            }

            // Un proveedor puede devolver un bloque anidado con el mismo
            // nombre que un campo simple (data.licencia es un array en
            // 'alpha'). No es un valor utilizable, se sigue buscando.
            if (is_array($data[$key]) || is_object($data[$key])) {
                continue;
            }

            return $data[$key];
        }

        return $default;
    }

    /**
     * Normaliza la respuesta de una consulta de placa vehicular.
     * Las llaves de salida coinciden con las del maestro de vehiculos
     * (transports) para poder autocompletar el formulario.
     *
     * @param array $data
     * @return array
     */
    protected function transformPlaca($data)
    {
        return [
            'plate_number' => strtoupper((string) $this->firstOf($data, ['placa', 'numero_de_placa', 'plate_number', 'plate'])),
            'brand' => $this->firstOf($data, ['marca', 'brand']),
            'model' => $this->firstOf($data, ['modelo', 'model']),
            'year' => $this->firstOf($data, ['anio_fabricacion', 'anio', 'ano_fabricacion', 'year']),
            'color' => $this->firstOf($data, ['color']),
            'owner' => $this->firstOf($data, ['propietario', 'nombre_propietario', 'owner']),
            'category' => $this->firstOf($data, ['categoria', 'clase', 'category']),
            'serie' => $this->firstOf($data, ['serie', 'numero_serie', 'vin']),
            'engine' => $this->firstOf($data, ['motor', 'numero_motor', 'engine']),
            'state' => $this->firstOf($data, ['estado', 'state']),
            'raw' => $data,
        ];
    }

    /**
     * Normaliza la respuesta de una consulta de licencia de conducir.
     * Las llaves de salida coinciden con las del maestro de conductores.
     *
     * @param array $data
     * @return array
     */
    protected function transformLicencia($data)
    {
        // 'alpha' anida los datos de la licencia en data.licencia y deja el
        // documento y el nombre al mismo nivel; 'beta' los devuelve planos.
        // Se lee primero el bloque anidado y se cae al nivel superior.
        $licencia = (isset($data['licencia']) && is_array($data['licencia'])) ? $data['licencia'] : [];

        $license = $this->firstOf(
            $licencia,
            ['numero', 'numero_licencia', 'licencia', 'license'],
            $this->firstOf($data, ['numero_licencia', 'licencia', 'license'])
        );

        return [
            'license' => strtoupper((string) $license),
            'number' => $this->firstOf($data, ['dni', 'numero_documento', 'number']),
            'name' => $this->firstOf($data, ['nombre_completo', 'nombre_o_razon_social', 'name']),
            'category' => $this->firstOf($licencia, ['categoria', 'clase_categoria', 'category'], $this->firstOf($data, ['categoria', 'clase_categoria', 'category'])),
            'state' => $this->firstOf($licencia, ['estado', 'state'], $this->firstOf($data, ['estado', 'state'])),
            'restrictions' => $this->firstOf($licencia, ['restricciones', 'restrictions'], $this->firstOf($data, ['restricciones', 'restrictions'])),
            'expiration_date' => $this->firstOf($licencia, ['fecha_vencimiento', 'fecha_hasta', 'expiration_date'], $this->firstOf($data, ['fecha_vencimiento', 'fecha_hasta', 'expiration_date'])),
            'issue_date' => $this->firstOf($licencia, ['fecha_expedicion', 'fecha_desde', 'issue_date'], $this->firstOf($data, ['fecha_expedicion', 'fecha_desde', 'issue_date'])),
            'raw' => $data,
        ];
    }

    public function service($type, $number)
    {
        // Interceptar y redirigir a SystemExtraServices si está activo
        $shouldUseApidocs = $this->shouldUseExtraService();
        \Log::info('ServiceData - Interceptor check', [
            'shouldUseApidocs' => $shouldUseApidocs,
            'type' => $type,
            'number' => $number
        ]);

        $apidocs_types = $this->apidocsTypes();
        $apidocs_only = in_array($type, $this->apidocsOnlyTypes());

        if ($shouldUseApidocs && isset($apidocs_types[$type])) {
            try {
                \Log::info('ServiceData - Usando apidocs');
                $apidocsService = new ApidocsService();
                $method = $apidocs_types[$type];

                $response = $apidocsService->$method($number);

                \Log::info('ServiceData - Respuesta de apidocs', ['response' => $response]);

                // Incrementar contador de consumo del cliente. Solo cuenta la
                // consulta cuando el servicio respondio; una validacion local
                // fallida (longitud de placa, dni, etc.) no consume cuota.
                if (!empty($response['success'])) {
                    ApidocsHelper::incrementUsage();
                }

                // Transformar la respuesta al formato esperado por el sistema
                return $this->transformExtraServiceResponse($response, $type);
            } catch (\Exception $e) {
                // Si falla el nuevo servicio, caer al servicio tradicional
                \Log::error('ServiceData - Error en apidocs, usando fallback', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }

        // placa, licencia y ce solo existen en apidocs. Sin ese servicio no hay
        // a donde caer, el proveedor tradicional no expone esos endpoints.
        if ($apidocs_only) {
            return [
                'success' => false,
                'message' => 'El servicio de consulta de '.$type.' no está disponible. Active el servicio de consultas adicionales.',
            ];
        }


        $res = $this->client->request('GET', '/api/' . $type . '/' . $number, $this->parameters);
        $response = json_decode($res->getBody()->getContents(), true);

        $res_data = [];
        if ($response['success']) {
            $data = $response['data'];
            if ($type === 'dni') {
                $department_id = '';
                $province_id = null;
                $district_id = null;
                $address = null;
                if (key_exists('source', $response) && $response['source'] === 'apiperu.dev') {
                    if (strlen($data['ubigeo_sunat'])) {
                        $department_id = $data['ubigeo'][0];
                        $province_id = $data['ubigeo'][1];
                        $district_id = $data['ubigeo'][2];
                        $address = $data['direccion'];
                    }
                } else {
                    $department_id = $data['ubigeo'][0];
                    $province_id = $data['ubigeo'][1];
                    $district_id = $data['ubigeo'][2];
                    $address = $data['direccion'];
                }

                $res_data = [
                    'name' => $data['nombre_completo'],
                    'trade_name' => '',
                    'location_id' => [
                        $department_id,
                        $province_id,
                        $district_id
                    ],
                    'address' => $address,
                    'department_id' => $department_id,
                    'province_id' => $province_id,
                    'district_id' => $district_id,
                    'condition' => '',
                    'state' => '',
                ];
            }

            if ($type === 'ruc') {
                $address = '';
                $department_id = null;
                $province_id = null;
                $district_id = null;
                if (key_exists('source', $response) && $response['source'] === 'apiperu.dev') {
                    if (strlen($data['ubigeo_sunat'])) {
                        $department_id = $data['ubigeo'][0];
                        $province_id = $data['ubigeo'][1];
                        $district_id = $data['ubigeo'][2];
                        $address = $data['direccion'];
                    }
                } else {
                    $department_id = $data['ubigeo'][0];
                    $province_id = $data['ubigeo'][1];
                    $district_id = $data['ubigeo'][2];
                    $address = $data['direccion'];
                }

                $is_agent_retention = $this->getAgentRetention($response, $data);
                $res_data = [
                    'name' => $data['nombre_o_razon_social'],
                    'trade_name' => '',
                    'address' => $address,
//                        'department_id' => $department_id,
//                        'province_id' => $province_id,
//                        'district_id' => $district_id,
                    'location_id' => $data['ubigeo'],
                    'condition' => $data['condicion'],
                    'state' => $data['estado'],
                    'is_agent_retention' => $is_agent_retention,
                ];
            }
            $response['data'] = $res_data;
        }
        $this->saveService(1, $response);
        return $response;
    }

    /**
     * Consulta los establecimientos anexos de un RUC.
     * Endpoint: POST /api/ruc-establecimientos-anexos  (body: ruc)
     *
     * @param string $number
     * @return array
     */
    public function establishments($number)
    {
        $this->parameters['form_params'] = ['ruc' => $number];
        $res = $this->client->request('POST', '/api/ruc-establecimientos-anexos', $this->parameters);
        $response = json_decode($res->getBody()->getContents(), true);

        $this->saveService(6, $response);

        return $response;
    }

    public function massive_validate_cpe($data)
    {
        $this->parameters['form_params'] = $data;
        $res = $this->client->request('POST', '/api/validacion_multiple_cpe', $this->parameters);
        $this->trackApi->push();
        $this->saveService(2);

        return json_decode($res->getBody()->getContents(), true);
    }
    private function getAgentRetention($response, $data)
    {
        $is_agent_retention = false;

        if(isset($data['es_agente_de_retencion']))
        {
            $is_agent_retention = ($data['es_agente_de_retencion'] === 'SI');
        }
        else if(isset($response['es_agente_de_retencion']))
        {
            $is_agent_retention = ($response['es_agente_de_retencion'] === 'SI');
        }

        return $is_agent_retention;
    }

    public function cpe($company_number, $document_type_id, $series, $number, $date_of_issue, $total)
    {
        $form_params = [
            'ruc_emisor' => $company_number,
            'codigo_tipo_documento' => $document_type_id,
            'serie_documento' => $series,
            'numero_documento' => $number,
            'fecha_de_emision' => $date_of_issue,
            'total' => $total
        ];

        $this->parameters['form_params'] = $form_params;
        $res = $this->client->request('POST', '/api/cpe', $this->parameters);
        $this->saveService(3);

        return json_decode($res->getBody()->getContents(), true);
    }

    public function exchange($date)
    {
        $exchange = ExchangeRate::query()->where('date', $date)->first();
        if ($exchange) {
            return [
                'date' => $date,
                'purchase' => $exchange->purchase,
                'sale' => $exchange->sale
            ];
        }
        $form_params = [
            'fecha' => $date,
        ];

        $this->parameters['form_params'] = $form_params;
        $res = $this->client->request('POST', '/api/tipo_de_cambio', $this->parameters);
        $response = json_decode($res->getBody()->getContents(), true);

        if ($response['success']) {
            $data = $response['data'];
            ExchangeRate::query()->create([
                'date' => $data['fecha_busqueda'],
                'date_original' => $data['fecha_sunat'],
                'sale_original' => $data['venta'],
                'sale' => $data['venta'],
                'purchase_original' => $data['compra'],
                'purchase' => $data['compra'],
            ]);

            return [
                'date' => $data['fecha_busqueda'],
                'purchase' => $data['compra'],
                'sale' => $data['venta']
            ];
        }
        $this->saveService(4);

        return [
            'date' => $date,
            'purchase' => 1,
            'sale' => 1,
        ];
    }

    public function printer_ticket($data)
    {
        $this->parameters['form_params'] = $data;
        $res = $this->client->request('POST', '/api/printer_ticket', $this->parameters);
        $this->saveService(5);

        return json_decode($res->getBody()->getContents(), true);
    }
}
