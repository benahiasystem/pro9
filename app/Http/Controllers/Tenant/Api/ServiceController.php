<?php

    namespace App\Http\Controllers\Tenant\Api;

    use App\CoreFacturalo\Helpers\Storage\StorageDocument;
    use App\CoreFacturalo\Services\Dni\Dni;
    use App\CoreFacturalo\Services\Ruc\Sunat;
    use App\Http\Controllers\Controller;
    use App\Http\Requests\Tenant\ServiceRequest;
    use App\Models\Tenant\Catalogs\Department;
    use App\Models\Tenant\Catalogs\District;
    use App\Models\Tenant\Catalogs\Province;
    use App\Models\Tenant\Document;
    use Exception;
    use Illuminate\Http\Request;


    class ServiceController extends Controller
    {


        public const ACCEPTED = '05';
        protected $wsClient;
        use StorageDocument;
        protected $document;



        /**
         * @param int $number
         *
         * @return array
         * @deprecated usar modules/ApiPeruDev/Data/ServiceData.php
         */
        public function ruc($number)
        {
            $service = new Sunat();
            $res = $service->get($number);
            if ($res) {
                // ######## INICIO CAMBIO GEOPOLITICO VENEZUELA
                try {
                    $departmentId = Department::idByDescription($res->departamento);
                    $provinceId = Province::idByDescription($res->provincia, $departmentId);
                    $districtId = District::idByDescription($res->distrito, $provinceId);

                    return [
                        'success' => true,
                        'data' => [
                            'name' => $res->razonSocial,
                            'trade_name' => $res->nombreComercial,
                            'address' => $res->direccion,
                            'phone' => implode(' / ', $res->telefonos),
                            'department' => $res->departamento,
                            'department_id' => $departmentId,
                            'province' => $res->provincia,
                            'province_id' => $provinceId,
                            'district' => $res->distrito,
                            'district_id' => $districtId,
                        ],
                    ];
                } catch (\InvalidArgumentException $exception) {
                    return [
                        'success' => false,
                        'message' => $exception->getMessage(),
                    ];
                }
                // ######## FIN CAMBIO GEOPOLITICO VENEZUELA
            } else {
                return [
                    'success' => false,
                    'message' => $service->getError()
                ];
            }
        }


        /**
         * @param int $number
         *
         * @return array
         *
         * @deprecated usar modules/ApiPeruDev/Data/ServiceData.php
         */
        public function dni($number)
        {
            $res = Dni::search($number);

            return $res;
        }

        // ######## INICIO API BCV ########
        public function exchangeRateTest($date)
        {
            return app(\App\Services\ExchangeRates\TenantExchangeRateService::class)->exchange($date);
        }

        public function exchange_rate(Request $request)
        {
            $rate = $this->exchangeRateTest($request->input('cur_date'));
            return ['success' => true, 'message' => 'Tipo de cambio disponible.',
                'data' => [$rate['date'] => ['buy' => $rate['purchase'], 'sell' => $rate['sale']]]];
        }

        public function searchExchangeRateByDate(Request $request)
        {
            return $this->exchangeRateTest($request->input('date', $request->input('cur_date')));
        }
        // ######## FIN API BCV ########

        public function documentStatus(Request $request)
        {
            if ($request->has('external_id') or $request->has('serie_number')) {
                $external_id = $request->input('external_id');
                $request_serie = $request->input('serie_number');
                $query = Document::query();
                if ($external_id) $query->where('external_id', $external_id);
                if ($request_serie !== null && $request_serie !== '') {
                    $parts = \App\Services\Fiscal\FiscalIdentity::parseNumberFull((string) $request_serie);
                    if ($parts === null) throw new Exception('Indique un número de factura válido.');
                    $serie = \App\Services\SeriesNumbering::normalizeCode($parts[0]);
                    $query->where('series', $serie)->where('number', $parts[1]);
                    if ($serie === '' && !$external_id) {
                        $branch = $request->input('establishment_id') ?? optional(auth()->user())->establishment_id;
                        if (!$branch) throw new Exception('Indique la sucursal de la factura sin serie.');
                        $query->where('establishment_id', $branch)->where('document_type_id', $request->input('document_type_id', '01'));
                    }
                }
                $document = $query->first();

                if (!$document) {
                    throw new Exception("El documento con código externo {$external_id} o numero {$request_serie}, no se encuentra registrado.");
                }
                return [
                    'success' => true,
                    'data' => [
                        'number' => $document->number_full,
                        'filename' => $document->filename,
                        'external_id' => $document->external_id,
                        'status_id' => $document->state_type_id,
                        'status' => $document->state_type->description,
                        'number_to_letter' => $document->number_to_letter,
                    ],
                    'links' => [
                        'pdf' => $document->download_external_pdf,
                    ],
                ];
            }
        }

    }
