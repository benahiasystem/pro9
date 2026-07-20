<?php

namespace Modules\Payment\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Payment\Http\Resources\PaymentConfigurationResource;
use Modules\Payment\Models\PaymentConfiguration;
use Modules\Payment\Http\Requests\PaymentConfigurationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\Finance\Helpers\UploadFileHelper;


class PaymentConfigurationController extends Controller
{

    /**
     * @return PaymentConfigurationResource
     */
    public function record()
    {
        return new PaymentConfigurationResource(PaymentConfiguration::firstOrFail());
    }


    /**
     * Devuelve el access token completo de Mercado Pago para la sesión actual.
     *
     * @return array|\Illuminate\Http\JsonResponse
     */
    public function accessTokenMp()
    {
        $accessToken = PaymentConfiguration::query()
            ->value('access_token_mp');

        if (empty($accessToken)) {
            return response()->json([
                'success' => false,
                'message' => 'No hay token de acceso configurado',
            ], 404);
        }

        return [
            'success' => true,
            'access_token_mp' => $accessToken,
        ];
    }


    /**
     * @return array
     */
    public function recordPermissions()
    {
        return [
            'data' => PaymentConfiguration::getPaymentPermissions()
        ];
    }


    /**
     * Actualizar configuracion
     *
     * @param  PaymentConfigurationRequest $request
     * @return array|\Illuminate\Http\JsonResponse
     */
    public function store(PaymentConfigurationRequest $request)
    {
        try {
            $type = $request->type;
            $record = PaymentConfiguration::firstOrFail();

            Log::info('PaymentConfiguration store: inicio', [
                'type' => $type,
                'record_id' => $record->id,
            ]);

            $response = match ($type) {
                '01' => $this->setDataYape($record, $request),
                '02' => $this->setDataMP($record, $request),
                '03' => $this->setDataCulqi($record, $request),
                '04' => $this->setDataIzipay($record, $request),
                default => [
                    'success' => false,
                    'message' => 'Tipo de pasarela no válido',
                ],
            };

            // Si el setter indicó fallo de negocio, no persistir.
            if (is_array($response) && array_key_exists('success', $response) && $response['success'] === false) {
                return $response;
            }

            $record->save();

            Log::info('PaymentConfiguration store: guardado OK', [
                'type' => $type,
                'record_id' => $record->id,
            ]);

            return $response ?: [
                'success' => true,
                'message' => 'Configuración actualizada',
            ];
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('PaymentConfiguration store failed', [
                'type' => $request->type,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al guardar la configuración de pagos: '.$e->getMessage(),
            ], 500);
        }
    }


    /**
     *
     * @param  PaymentConfiguration $record
     * @param  PaymentConfigurationRequest $request
     * @return array
     */
    public function setDataMP(PaymentConfiguration &$record, $request)
    {
        $record->enabled_mp = $request->enabled_mp;
        $record->public_key_mp = $request->public_key_mp;

        if ($request->filled('access_token_mp')) {
            $record->access_token_mp = $request->access_token_mp;
        }

        return [
            'success' => true,
            'message' => 'Configuración actualizada'
        ];
    }

    public function setDataCulqi(PaymentConfiguration &$record, $request)
    {
        $enableCulqi = (bool) $request->enabled_culqi;
        $enableIzipay = (bool) $record->enabled_izipay;

        // Si se está activando Culqi, apagar Izipay automáticamente en lugar de bloquear
        if ($enableCulqi && $enableIzipay) {
            $record->enabled_izipay = false;
        }

        $record->enabled_culqi = $enableCulqi;

        if ($record->enabled_culqi) {
            $record->enabled_izipay = false;
        }

        if ($request->publickey_culqi) {
            $record->publickey_culqi = $request->publickey_culqi;
        }

        if ($request->privatekey_culqi) {
            $record->privatekey_culqi = $request->privatekey_culqi;
        }

        if ($request->idrsa_culqi) {
            $record->idrsa_culqi = $request->idrsa_culqi;
        }
    
        if ($request->rsa_culqi) {
            $record->rsa_culqi = $request->rsa_culqi;
        }

        return [
            'success' => true,
            'message' => 'Configuración actualizada'
        ];
    }

    public function setDataIzipay(PaymentConfiguration &$record, $request)
    {
        $enableIzipay = (bool) $request->enabled_izipay;
        $enableCulqi = (bool) $record->enabled_culqi;

        // Si se está activando Izipay, apagar Culqi automáticamente en lugar de bloquear
        if ($enableIzipay && $enableCulqi) {
            $record->enabled_culqi = false;
        }

        $record->enabled_izipay = $enableIzipay;

        if ($record->enabled_izipay) {
            $record->enabled_culqi = false;
        }

        if ($request->username_izipay) {
            $record->username_izipay = trim($request->username_izipay);
        }

        if ($request->password_izipay) {
            $record->password_izipay = trim($request->password_izipay);
        }

        if ($request->publickey_izipay) {
            $record->publickey_izipay = PaymentConfiguration::sanitizePublicKeyForStorage($request->publickey_izipay);
        }

        if ($request->sha256key_izipay) {
            $record->sha256key_izipay = trim($request->sha256key_izipay);
        }

        return [
            'success' => true,
            'message' => 'Configuración actualizada'
        ];
    }

    /**
     *
     * @param  PaymentConfiguration $record
     * @param  PaymentConfigurationRequest $request
     * @return array
     */
    public function setDataYape(PaymentConfiguration &$record, $request)
    {
        $record->enabled_yape = $request->enabled_yape;
        $record->name_yape = $request->name_yape;
        $record->telephone_yape = $request->telephone_yape;

        if ($request->qrcode_yape && $request->temp_path_yape) {
            $filename = UploadFileHelper::uploadFileFromTempFile(
                'payment_configurations',
                $request->qrcode_yape,
                $request->temp_path_yape,
                $record->id,
                'qr_yape'
            );
            $record->qrcode_yape = $filename;
        }

        return [
            'success' => true,
            'message' => 'Configuración actualizada',
        ];
    }


    /**
     * Cargar qr yape
     *
     * @param  Request $request
     * @return array
     */
    public function uploadQrcodeYape(Request $request)
    {

        $validate_upload = UploadFileHelper::validateUploadFile($request, 'file', 'jpg,jpeg,png,svg,webp');
        if(!$validate_upload['success']) return $validate_upload;

        if ($request->hasFile('file'))
        {
            $new_request = [
                'file' => $request->file('file'),
                'type' => $request->input('type'),
            ];

            return UploadFileHelper::getTempFile($new_request);
        }

        return [
            'success' => false,
            'message' =>  __('app.actions.upload.error'),
        ];
    }


}