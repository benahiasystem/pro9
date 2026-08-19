<?php

namespace App\Http\Controllers\System;

// ########## INICIO CAMBIO RIF SUPER ADMIN
use App\Exceptions\System\RifLookupException;
// ######### FIN CAMBIO RIF SUPER ADMIN
use App\Http\Controllers\Controller;
use App\Models\System\Configuration;
// ########## INICIO CAMBIO RIF SUPER ADMIN
use App\Services\System\RifLookupService;
use App\Support\System\Rif;
// ######### FIN CAMBIO RIF SUPER ADMIN
use Modules\Services\Data\ServiceData;
use App\CoreFacturalo\Services\Ruc\Sunat;
class ServiceController extends Controller
{
	public function ruc($number)
	{
		$configuration = Configuration::first();
		if (!$configuration->token_apiruc || $configuration->token_apiruc === 'false') {
			$service = new Sunat();
			$res     = $service->get($number);
			if ($res) {
				return [
					'success' => true,
					'data'    => [
						'name'       => $res->razonSocial,
						'trade_name' => $res->nombreComercial,
					]
				];
			} else {
				return [
					'success' => false,
					'message' => $service->getError()
				];
			}
		} else {
			try {
				$data     = ServiceData::service('ruc', $number);

				if($data["success"]){
                    $response = [
                        'success' => true,
                        'data' => [
                            'name' => $data['data']['nombre_o_razon_social'],
                            'trade_name' => $data['data']['nombre_o_razon_social'],
                        ]
                    ];
                    return response()->json($response, 200);
                }

                $response = [
                    'success' => false,
                    'message' => $data["message"]
                ];
                return response()->json($response, 200);
			} catch (\Throwable $th) {
				return response()->json([
					'success' => false,
					'message' => 'El número de RUC ingresado no existe. Detalles: ' . $th->getMessage()
				], 200);
			}
		}
	}

    // ########## INICIO CAMBIO RIF SUPER ADMIN
    public function rif(string $rif, RifLookupService $service)
    {
        $normalizedRif = Rif::normalize($rif);

        if (!Rif::isValid($normalizedRif)) {
            return response()->json([
                'success' => false,
                'message' => 'El RIF debe contener un prefijo V, E, J, P o G y nueve dígitos.',
            ], 422);
        }

        try {
            return response()->json([
                'success' => true,
                'data' => $service->lookup($normalizedRif),
            ]);
        } catch (RifLookupException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], $exception->responseStatus());
        }
    }
    // ######### FIN CAMBIO RIF SUPER ADMIN
}
