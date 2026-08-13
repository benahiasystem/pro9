<?php

namespace Modules\ApiPeruDev\Http\Controllers;

use App\CoreFacturalo\Helpers\Storage\StorageDocument;
use App\CoreFacturalo\Helpers\Xml\XmlFormat;
use App\CoreFacturalo\Template;
use App\CoreFacturalo\Facturalo;
use App\Models\Tenant\Company;
use App\Models\Tenant\Dispatch;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\ApiPeruDev\Helpers\CdrRead;
use Modules\ApiPeruDev\Helpers\ServiceDispatch;
use Modules\Store\Helpers\StorageHelper;
use Modules\PseService\Http\Gior\Service as GiorService;
use Modules\PseService\Http\Gior\ServiceSendFact as ServiceSendFact; 
use Exception;
use Modules\PseService\Http\Gior\ServiceOseSendFact;

class ServiceDispatchController extends Controller
{
    use StorageDocument;

    const STATE_REGISTERED = '01';
    const STATE_SENT = '03';
    const STATE_ACCEPTED = '05';
    const STATE_OBSERVED = '07';
    const STATE_REJECTED = '09';

    /**
     * Clasifica el código de respuesta del CDR segun los rangos de SUNAT.
     *
     * 0            -> Aceptado
     * 0100 - 1999  -> Excepción (SUNAT no proceso el comprobante)
     * 2000 - 3999  -> Rechazado
     * 4000 a mas   -> Aceptado con observaciones (sigue siendo aceptado)
     *
     * @param  string|int|null $code
     * @param  string          $default Estado a conservar si el codigo no es numerico o es una excepcion
     * @return string
     */
    private function getStateTypeByCdrCode($code, $default = self::STATE_ACCEPTED)
    {
        if ($code === null || $code === '' || !is_numeric($code)) {
            return $default;
        }

        $code = (int)$code;

        if ($code === 0) {
            return self::STATE_ACCEPTED;
        }

        if ($code < 2000) {
            return $default;
        }

        if ($code < 4000) {
            return self::STATE_REJECTED;
        }

        return self::STATE_ACCEPTED;
    }

    /**
     * Las observaciones normalmente NO llegan en el ResponseCode (que es 0),
     * sino en los nodos cbc:Note del CDR con codigos de la serie 4000.
     *
     * @param  string|int|null $code
     * @param  array           $notes
     * @return bool
     */
    private function hasCdrObservations($code, array $notes)
    {
        if (!empty($notes)) {
            return true;
        }

        return is_numeric($code) && (int)$code >= 4000;
    }

    /**
     * Tipo de alerta para el frontend segun el estado del comprobante
     *
     * @param  string $state_type_id
     * @param  bool   $observed
     * @return string
     */
    private function getResponseTypeByStateType($state_type_id, $observed = false)
    {
        if ($state_type_id === self::STATE_REJECTED) {
            return 'error';
        }

        if ($observed) {
            return 'warning';
        }

        switch ($state_type_id) {
            case self::STATE_ACCEPTED:
                return 'success';
            case self::STATE_OBSERVED:
                return 'warning';
            default:
                return 'info';
        }
    }

    /**
     * @param  string $state_type_id
     * @param  bool   $observed
     * @return string
     */
    private function getStateTypeDescription($state_type_id, $observed = false)
    {
        if ($observed && $state_type_id === self::STATE_ACCEPTED) {
            return 'Aceptado con observaciones';
        }

        $states = [
            self::STATE_REGISTERED => 'Registrado',
            self::STATE_SENT => 'Enviado',
            self::STATE_ACCEPTED => 'Aceptado',
            self::STATE_OBSERVED => 'Observado',
            self::STATE_REJECTED => 'Rechazado',
        ];

        return $states[$state_type_id] ?? 'Desconocido';
    }

    /**
     * Estructura unica de respuesta para la consulta de ticket
     *
     * @param  Dispatch $record
     * @param  array    $data
     * @param  bool     $simple_result
     * @return array
     */
    private function buildStatusTicketResponse($record, array $data, $simple_result = false)
    {
        $state_type_id = $data['state_type_id'] ?? $record->state_type_id;
        $has_cdr = $data['has_cdr'] ?? false;
        $notes = $data['notes'] ?? [];
        $observed = $this->hasCdrObservations($data['sunat_code'] ?? null, $notes);

        $response = [
            'success' => $data['success'],
            'data' => [
                'number' => $record->number_full,
                'filename' => $record->filename,
                'external_id' => $record->external_id,
                'state_type_id' => $state_type_id,
            ],
            'state_type_id' => $state_type_id,
            'state_description' => $this->getStateTypeDescription($state_type_id, $observed),
            'response_type' => $data['response_type'] ?? $this->getResponseTypeByStateType($state_type_id, $observed),
            'sunat_code' => $data['sunat_code'] ?? null,
            'observed' => $observed,
            'notes' => $notes,
            'has_cdr' => $has_cdr,
            'message' => $data['message'],
        ];

        if (!$simple_result) {
            $response['links'] = [
                'xml' => $record->download_external_xml,
                'pdf' => $record->download_external_pdf,
                'cdr' => $has_cdr ? $record->download_external_cdr : null,
            ];
        }

        return $response;
    }

    /**
     * Registra el error de comunicacion/formato y devuelve la respuesta al frontend
     *
     * @param  Dispatch $dispatch
     * @param  string   $code
     * @param  string   $message
     * @param  bool     $simple_result
     * @return array
     */
    private function registerStatusTicketError($dispatch, $code, $message, $simple_result = false)
    {
        Dispatch::query()
            ->where('id', $dispatch->id)
            ->update([
                'sunat_error_response' => json_encode([
                    'codigo' => $code,
                    'descripcion' => 'Error de sunat',
                    'mensaje' => $message
                ])
            ]);

        return $this->buildStatusTicketResponse($dispatch, [
            'success' => false,
            'state_type_id' => $dispatch->state_type_id,
            'response_type' => 'error',
            'message' => $message,
            'sunat_code' => $code,
        ], $simple_result);
    }

    public function getServiceInitial()
    {
        $cp = Company::query()
            ->select('number', 'soap_type_id', 'soap_sunat_username', 'soap_sunat_password', 'api_sunat_id', 'api_sunat_secret')
            ->first();

        $serviceDispatch = new ServiceDispatch();
        $serviceDispatch->setCredentials(
            $cp->number,
            ($cp->soap_type_id === '01'),
            $cp->soap_sunat_username,
            $cp->soap_sunat_password,
            $cp->api_sunat_id,
            $cp->api_sunat_secret
        );

        return $serviceDispatch;
    }

    public function send($external_id)
    {
        DB::connection('tenant')->beginTransaction();
        try {

            $dispatch = Dispatch::query()
                ->select('id', 'document_type_id', 'series', 'number', 'filename', 'ticket')
                ->where('external_id', $external_id)->first();
            if ($dispatch) {
                $xml_signed = (new StorageHelper())->getXmlSigned($dispatch->filename);

                $facturalo = new Facturalo();
                $hasPseSend = $facturalo->hasPseSend();
                // dd($hasPseSend);
                $company = Company::first();
                if ($company->pse_provider_id == 4) {
                    $service = new ServiceSendFact();
                } else {
                    $service = new GiorService();
                }
                if($hasPseSend){
                
                    $response = $service->sendXmlSigned($dispatch->filename, $xml_signed, true);

                    if(!$response['success']) {
                        $res_errors = is_array($response['errors']) ? implode(" ",$response['errors']) : '';
                        throw new Exception(json_encode([
                            'codigo' => $response['code'] ?? 'Error PSE',
                            'descripcion' => $response['message'] ?? 'Error desconocido',
                            'mensaje' => $res_errors,
                        ]));
                    } else {
                        Dispatch::query()
                            ->where('id', $dispatch->id)
                            ->update([
                                // 'ticket' => $response['ticket'],
                                'state_type_id' => '03'
                            ]);
                        DB::connection('tenant')->commit();
                    }

                    return [
                        'success' => true,
                        'message' => 'PSE - Se obtuvo el nro. de ticket correctamente',
                    ];
                } else {

                    // Ose sendfact a través de api rest 
                    if ($company->soap_send_id === '04') {
                        $ose = new ServiceOseSendFact;
                        $response = $ose->sendXmlSigned($dispatch->filename, $xml_signed);
                        if(!$response['success']) {
                            $res_errors = is_array($response['errors']) ? implode(" ",$response['errors']) : '';
                            throw new Exception(json_encode([
                                'codigo' => $response['code'] ?? 'Error OSE',
                                'descripcion' => $response['message'] ?? 'Error desconocido',
                                'mensaje' => $res_errors,
                            ]));
                        } else {
                            Dispatch::query()
                                ->where('id', $dispatch->id)
                                ->update([
                                    // 'ticket' => $response['ticket'],
                                    'state_type_id' => '03'
                                ]);
                            DB::connection('tenant')->commit();
                        }

                            return [
                                'success' => true,
                                'message' => 'PSE - Se obtuvo el nro. de ticket correctamente',
                            ];


                    } else {
                        $res = $this->getServiceInitial()->send(
                            $dispatch->filename,
                            $xml_signed
                        );

                        if ($res['success']) {
                            $data = $res['data'];
                            Log::info("Dispatch { $dispatch->filename } send response: ", $data);
                            if (is_array($data) && array_key_exists('numTicket', $data)) {
                                $ticket = $data['numTicket'];
                                $reception_date = $data['fecRecepcion'];
                                $updated = Dispatch::where('id', $dispatch->id)
                                    ->update([
                                        'ticket' => $ticket,
                                        'reception_date' => $reception_date,
                                        'state_type_id' => '03'
                                    ]);
                                Log::info("Dispatch update result: " . $updated);
                                DB::connection('tenant')->commit();
                                return [
                                    'success' => true,
                                    'message' => "Se obtuvo el nro. de ticket correctamente. Ticket: {$ticket}, Fecha de recepción: {$reception_date}, ID guia: {$dispatch->id}",
                                ];
                            } else {
                                Log::error('No se obtuvo ticket', $res);
                                throw new Exception(json_encode([
                                    'codigo' => 'Sin Ticket',
                                    'descripcion' => 'No se obtuvo el ticket adecuadamente',
                                    'mensaje' => 'La trama de la SUNAT no devolvió numTicket'
                                ]));
                            }
                        } else {
                            Log::error('No se obtuvo ticket', $res);
                            $error_message = (is_array($res['message'] ?? '')) ? implode(" ", $res['message']) : ($res['message'] ?? 'Error desconocido');
                            throw new Exception(json_encode([
                                'codigo' => 'Error SUNAT',
                                'descripcion' => 'Rechazo o Fallo en Servidor (Send)',
                                'mensaje' => $error_message
                            ]));
                        }

                    }
                }
            }
            return [
                'success' => false,
                'message' => 'El external id es incorrecto'
            ];
        } catch (\Exception $e) {
            DB::connection('tenant')->rollBack();
            $errorMessage = $e->getMessage();
            Log::error($errorMessage);
            
            $errorLog = [
                'codigo' => 'Error Interno',
                'descripcion' => 'Excepción no controlada',
                'mensaje' => $errorMessage
            ];

            $decoded = json_decode($errorMessage, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded) && isset($decoded['codigo'])) {
                $errorLog = $decoded;
            }
            
            if (isset($external_id)) {
                try {
                    $dispatchToUpdate = \App\Models\Tenant\Dispatch::where('external_id', $external_id)->first();
                    if ($dispatchToUpdate) {
                        $dispatchToUpdate->sunat_error_response = $errorLog;
                        $dispatchToUpdate->save();
                    }
                } catch (\Exception $e2) {
                    Log::error("Fallo al guardar log de error en Dispatch: " . $e2->getMessage());
                }
            }

            return [
                'success' => false,
                'message' => 'No fue posible enviar: ' . ($errorLog['descripcion'] ?? 'Error desconocido')
            ];
        }
    }

    public function statusTicket($external_id, $simple_result = false)
    {
        $dispatch = Dispatch::query()
            ->select('id', 'series', 'number', 'state_type_id', 'ticket', 'filename', 'external_id')
            ->where('external_id', $external_id)->first();

        if ($dispatch) {
            $storage = new StorageHelper();

            $facturalo = new Facturalo();
            $hasPseSend = $facturalo->hasPseSend();
            $company = Company::first();
            if ($company->pse_provider_id == 4) {
                $service = new ServiceSendFact();
            } else {
                $service = new GiorService();
            }

            if($hasPseSend){
                $response = $service->querySummary($dispatch->filename);
                if ($company->pse_provider_id == 4) {

                    if ($response['document_status'] == 4) {
                        return $this->buildStatusTicketResponse($dispatch, [
                            'success' => false,
                            'state_type_id' => $dispatch->state_type_id,
                            'response_type' => 'error',
                            'message' => "PSE. TICKET - Document Status: {$response['document_status']}; Message: {$response['message']} ",
                        ], $simple_result);
                    }

                    if(!$response['success']) {
                        throw new Exception("PSE. TICKET - Code: {$response['code']}; Description: {$response['message']}");
                    } else {
                        $message = $response['message'];
                        $state_type_id =  $service->validationCodeResponseIntegration($response['document_status'], $response['message']);
                        $has_cdr = false;
                        $qr_url = null;
                        $download_external_cdr = null;

                        if($response['cdr'] != null) {
                            $has_cdr = true;
                            $download_external_cdr = $dispatch->download_external_cdr;
                            $this->uploadStorage($dispatch->filename, $response['cdr'], 'cdr_b64');
                            $file_content_cdr = base64_decode($response['cdr']);
                            $storage->uploadCdr($dispatch->filename, $file_content_cdr);
                            $cdr_content = $storage->getCdr($dispatch->filename);
                            $res['cdr_data'] = (new CdrRead())->getCdrData($cdr_content);
                            $qr_url = $res['cdr_data']['qr_url'];
                        }

                        $dispatch->has_cdr = $has_cdr;
                        $dispatch->state_type_id = $state_type_id;
                        $dispatch->qr_url = $qr_url;
                        $dispatch->save();

                        return $this->buildStatusTicketResponse($dispatch, [
                            'success' => true,
                            'state_type_id' => $state_type_id,
                            'message' => 'PSE. '.$message,
                            'sunat_code' => isset($res['cdr_data']['code']) ? $res['cdr_data']['code'] : null,
                            'notes' => isset($res['cdr_data']['notes']) ? $res['cdr_data']['notes'] : [],
                            'has_cdr' => $has_cdr,
                        ], $simple_result);
                    }


                } else {
                        if ($response['code'] != 200) {
                            $message = array_key_exists('message', $response) ? $response['message'] : '';
                            $errors = array_key_exists('errores', $response) ? $response['errores'] : '';
                            return $this->buildStatusTicketResponse($dispatch, [
                                'success' => false,
                                'state_type_id' => $dispatch->state_type_id,
                                'response_type' => 'error',
                                'sunat_code' => $response['code'],
                                'message' => "PSE. TICKET - Code: {$response['code']}; Errores: {$message} - {$errors}",
                            ], $simple_result);
                        }

                        if(!$response['success']) {
                            throw new Exception("PSE. TICKET - Code: {$response['code']}; Description: {$response['message']}");
                        } else {
                            $message = $response['message'];
                            $state_type_id = '05';
                            $has_cdr = false;
                            $qr_url = null;
                            $download_external_cdr = null;

                            if($response['rejected']) {
                                $state_type_id = '09';
                            }
                            if($response['cdr'] != null) {
                                $has_cdr = true;
                                $download_external_cdr = $dispatch->download_external_cdr;
                                $cdr_content = base64_decode($response['cdr']);
                                $this->uploadStorage($dispatch->filename, $cdr_content, 'cdr_xml');
                                $cdr_content = $storage->getCdr($dispatch->filename);
                                $res['cdr_data'] = (new CdrRead())->getCdrData($cdr_content);

                                $qr_url = $res['cdr_data']['qr_url'];
                            }

                            $dispatch->has_cdr = $has_cdr;
                            $dispatch->state_type_id = $state_type_id;
                            $dispatch->qr_url = $qr_url;
                            $dispatch->save();

                            DB::connection('tenant')->commit();

                            return $this->buildStatusTicketResponse($dispatch, [
                                'success' => true,
                                'state_type_id' => $state_type_id,
                                'message' => 'PSE. '.$message,
                                'sunat_code' => isset($res['cdr_data']['code']) ? $res['cdr_data']['code'] : null,
                                'notes' => isset($res['cdr_data']['notes']) ? $res['cdr_data']['notes'] : [],
                                'has_cdr' => $has_cdr,
                            ], $simple_result);
                        }
                    }


                

            } else {
                    if ($company->soap_send_id === '04') {
                        $ose = new ServiceOseSendFact;
                        $response = $ose->querySummary($dispatch->filename);
                        if ($response['document_status'] == 4) {
                            return $this->buildStatusTicketResponse($dispatch, [
                                'success' => false,
                                'state_type_id' => $dispatch->state_type_id,
                                'response_type' => 'error',
                                'message' => "PSE. TICKET - Document Status: {$response['document_status']}; Message: {$response['message']} ",
                            ], $simple_result);
                        }

                        if(!$response['success']) {
                            throw new Exception("PSE. TICKET - Code: {$response['code']}; Description: {$response['message']}");
                        } else {
                            $message = $response['message'];
                            $state_type_id =  $ose->validationCodeResponseIntegration($response['document_status'], $response['message']);
                            $has_cdr = false;
                            $qr_url = null;
                            $download_external_cdr = null;
    
                            if($response['cdr'] != null) {
                                $has_cdr = true;
                                $download_external_cdr = $dispatch->download_external_cdr;
                                $this->uploadStorage($dispatch->filename, $response['cdr'], 'cdr_b64');
                                $file_content_cdr = (new CdrRead())->getCrdContent($response['cdr']);
                                $storage->uploadCdr($dispatch->filename, $file_content_cdr);
                                $cdr_content = $storage->getCdr($dispatch->filename);
                                $res['cdr_data'] = (new CdrRead())->getCdrData($cdr_content);
                                $qr_url = $res['cdr_data']['qr_url'];
                            }
    
                            $dispatch->has_cdr = $has_cdr;
                            $dispatch->state_type_id = $state_type_id;
                            $dispatch->qr_url = $qr_url;
                            $dispatch->save();

                            return $this->buildStatusTicketResponse($dispatch, [
                                'success' => true,
                                'state_type_id' => $state_type_id,
                                'message' => 'PSE. '.$message,
                                'sunat_code' => isset($res['cdr_data']['code']) ? $res['cdr_data']['code'] : null,
                                'notes' => isset($res['cdr_data']['notes']) ? $res['cdr_data']['notes'] : [],
                                'has_cdr' => $has_cdr,
                            ], $simple_result);
                        }


                    } else {
                        $res_ticket = $this->getServiceInitial()->ticket($dispatch->ticket);

                        // El helper envuelve la respuesta de SUNAT en success/data
                        if (!$res_ticket['success']) {
                            return $this->registerStatusTicketError($dispatch, 'TICKET', $res_ticket['message'], $simple_result);
                        }

                        $res = $res_ticket['data'];

                        if (!key_exists('codRespuesta', $res)) {
                            return $this->registerStatusTicketError(
                                $dispatch,
                                'TICKET',
                                'Respuesta no reconocida de SUNAT al consultar el ticket.',
                                $simple_result
                            );
                        }

                        $has_cdr = false;
                        $qr_url = null;
                        $state_type_id = '01';
                        $message = '';
                        $error_json = null;
                        $soap_shipping_response = null;
                        $sunat_code = null;
                        $notes = [];
                        $success = true;

                        switch ($res['codRespuesta']) {
                            case '98':
                                $state_type_id = '03';
                                $message = 'La guía aún está en proceso, vuelva a consultar.';
                                break;
                            case '0':
                                $state_type_id = '05';
                                $has_cdr = !empty($res['arcCdr']);
                                break;
                            case '99':
                                $state_type_id = '09';
                                if (($res['indCdrGenerado'] ?? '0') === '1' && !empty($res['arcCdr'])) {
                                    $has_cdr = true;
                                } else {
                                    $sunat_code = $res['error']['numError'] ?? null;
                                    $message = $res['error']['desError'] ?? 'La guía fue rechazada por SUNAT.';
                                    $error_json = [
                                        'codigo' => $sunat_code,
                                        'descripcion' => 'Error de sunat',
                                        'mensaje' => $message
                                    ];
                                }
                                break;
                            default:
                                $success = false;
                                $state_type_id = $dispatch->state_type_id;
                                $message = "Respuesta no reconocida de SUNAT (codRespuesta: {$res['codRespuesta']}).";
                                break;
                        }

                        if ($has_cdr) {
                            $file_content_cdr = (new CdrRead())->getCrdContent($res['arcCdr']);
                            $storage->uploadCdr($dispatch->filename, $file_content_cdr);
                            $cdr_content = (new StorageHelper())->getCdr($dispatch->filename);
                            $cdr_data = (new CdrRead())->getCdrData($cdr_content);

                            $qr_url = $cdr_data['qr_url'] ?? null;
                            $sunat_code = $cdr_data['code'] ?? null;
                            $notes = $cdr_data['notes'] ?? [];
                            $message = $cdr_data['message'] ?? $message;

                            // El código del CDR define aceptado / observado / rechazado
                            $state_type_id = $this->getStateTypeByCdrCode($sunat_code, $state_type_id);

                            $soap_shipping_response = [
                                'codigo' => $sunat_code,
                                'notes' => $notes,
                                'error' => '',
                                'mensaje' => $message
                            ];

                            if ($state_type_id === self::STATE_REJECTED) {
                                $error_json = [
                                    'codigo' => $sunat_code,
                                    'descripcion' => 'Rechazado por sunat',
                                    'mensaje' => $message
                                ];
                            }
                        }

                        $update = [
                            'state_type_id' => $state_type_id,
                            'qr_url' => $qr_url,
                            'has_cdr' => $has_cdr,
                            'sunat_error_response' => $error_json ? json_encode($error_json) : null,
                        ];

                        if ($soap_shipping_response !== null) {
                            $update['soap_shipping_response'] = json_encode($soap_shipping_response);
                        }

                        Dispatch::query()
                            ->where('id', $dispatch->id)
                            ->update($update);

                        $record = Dispatch::query()
                            ->select('id', 'series', 'number', 'state_type_id', 'filename', 'external_id')
                            ->where('external_id', $external_id)->first();

                        return $this->buildStatusTicketResponse($record, [
                            'success' => $success,
                            'state_type_id' => $state_type_id,
                            'message' => $message,
                            'sunat_code' => $sunat_code,
                            'notes' => $notes,
                            'has_cdr' => $has_cdr,
                        ], $simple_result);
                    }
            }

            return $res;
        }

        return [
            'success' => false,
            'message' => 'El external id es incorrecto'
        ];
    }


    public function createXmlUnsigned($document)
    {
        $template = new Template();
        $template_name = ($document['document_type_id'] === '31')?'dispatch_carrier':'dispatch';
        Log::info($template_name);
        $xmlUnsigned = XmlFormat::format($template->xml($template_name, null, $document));
        $this->uploadStorage($document['filename'], $xmlUnsigned, 'unsigned');

        return $xmlUnsigned;
    }

    public function getData($id)
    {
        $company = Company::query()
            ->first();

        $record = Dispatch::query()
            ->find($id);

        $items = [];
        foreach ($record->items as $it) {
            $items[] = [
                'internal_id' => $it->item->internal_id,
                'name' => $it->item->description,
                'unit_type_id' => $it->item->unit_type_id,
                'quantity' => $it->quantity,
                'weight' => $it->item->weight ?? null,
            ];
        }
        return [
            'company_name' => $company->name,
            'company_number' => $company->number,
            'company_trade_name' => $company->trade_name,
            'customer_identity_document_type_id' => optional($record->customer)->identity_document_type_id,
            'customer_number' => optional($record->customer)->number,
            'customer_name' => optional($record->customer)->name,
            'document_type_id' => $record->document_type_id,
            'series' => $record->series,
            'number' => $record->number,
            'date_of_issue' => $record->date_of_issue->format('Y-m-d'),
            'time_of_issue' => $record->time_of_issue,
            'transfer_reason_type_id' => $record->transfer_reason_type_id,
            'transfer_reason_type_name' => optional($record->transfer_reason_type)->description,
            'unit_type_id' => $record->unit_type_id,
            'total_weight' => $record->total_weight,
            'packages_number' => $record->packages_number,
            'transport_mode_type_id' => $record->transport_mode_type_id,
            'date_of_shipping' => $record->date_of_shipping->format('Y-m-d'),
            'observations' => $record->observations,
            'filename' => $record->filename,
            'origin_location_id' => optional($record->origin)->location_id,
            'origin_address' => optional($record->origin)->address,
            'origin_code' => optional($record->origin)->code,
            'delivery_location_id' => optional($record->delivery)->location_id,
            'delivery_address' => optional($record->delivery)->address,
            'delivery_code' => optional($record->delivery)->code,
            'driver_identity_document_type_id' => optional($record->driver)->identity_document_type_id,
            'driver_number' => optional($record->driver)->number,
            'driver_names' => optional($record->driver)->name,
            'driver_lastnames' => optional($record->driver)->name,
            'driver_license' => optional($record->driver)->license,
            'transport_plate_number' => $record->transport_data ? $record->transport_data['plate_number'] : null,
            'transport_tuc' => $record->transport_data ? $record->transport_data['tuc'] : null,
            'dispatcher_identity_document_type_id' => optional($record->dispatcher)->identity_document_type_id,
            'dispatcher_number' => optional($record->dispatcher)->number,
            'dispatcher_name' => optional($record->dispatcher)->name,
            'dispatcher_number_mtc' => optional($record->dispatcher)->number_mtc,

            'sender_identity_document_type_id' => $record->sender_data ? $record->sender_data['identity_document_type_id'] : null,
            'sender_number' => $record->sender_data ? $record->sender_data['number'] : null,
            'sender_name' => $record->sender_data ? $record->sender_data['name'] : null,

            'receiver_identity_document_type_id' => $record->receiver_data ? $record->receiver_data['identity_document_type_id'] : null,
            'receiver_number' => $record->receiver_data ? $record->receiver_data['number'] : null,
            'receiver_name' => $record->receiver_data ? $record->receiver_data['name'] : null,

            'sender_address_location_id' => $record->sender_address_data ? $record->sender_address_data['location_id'] : null,
            'sender_address_address' => $record->sender_address_data ? $record->sender_address_data['address'] : null,

            'receiver_address_location_id' => $record->receiver_address_data ? $record->receiver_address_data['location_id'] : null,
            'receiver_address_address' => $record->receiver_address_data ? $record->receiver_address_data['address'] : null,
            'payer_identity_document_type_id' => $record->payer ? $record->payer['identity_document_type_id'] : null,
            'payer_number' => $record->payer ? $record->payer['number'] : null,
            'payer_name' => $record->payer ? $record->payer['name'] : null,
            'payer_description' => $record->payer ? $record->payer['description'] : null,
            'items' => $items,
            'secondary_transports' => $record->secondary_transports,
            'secondary_drivers' => $record->secondary_drivers,
            'related_number' => optional($record->related)->number,
            'related_document_type_id' => optional($record->related)->document_type_id,
            'has_transport_driver_01' => $record->has_transport_driver_01,
            'is_transport_m1l' => $record->is_transport_m1l ? $record->is_transport_m1l : false,
            'license_plate_m1l' => $record->license_plate_m1l ? $record->license_plate_m1l : null,
            'reference_documents' => $record->reference_documents,
            'buyer_id' => $record->buyer_id,
            'buyer' => (array)$record->buyer,
        ];
    }

    public function getDataCarrier($id)
    {
        $company = Company::query()
            ->first();

        $record = Dispatch::query()
            ->find($id);

        $items = [];
        foreach ($record->items as $it) {
            $items[] = [
                'internal_id' => $it->item->internal_id,
                'name' => $it->item->description,
                'unit_type_id' => $it->item->unit_type_id,
                'quantity' => $it->quantity,
            ];
        }
        return [
            'company_name' => $company->name,
            'company_number' => $company->number,
            'company_trade_name' => $company->trade_name,
//            'customer_identity_document_type_id' => $record->customer->identity_document_type_id,
//            'customer_number' => $record->customer->number,
//            'customer_name' => $record->customer->name,
            'document_type_id' => $record->document_type_id,
            'series' => $record->series,
            'number' => $record->number,
            'date_of_issue' => $record->date_of_issue->format('Y-m-d'),
            'time_of_issue' => $record->time_of_issue,
//            'transfer_reason_type_id' => $record->transfer_reason_type_id,
//            'transfer_reason_type_name' => $record->transfer_reason_type->description,
            'unit_type_id' => $record->unit_type_id,
            'total_weight' => $record->total_weight,
//            'transport_mode_type_id' => $record->transport_mode_type_id,
            'date_of_shipping' => $record->date_of_shipping->format('Y-m-d'),
            'observations' => $record->observations,
            'filename' => $record->filename,
//            'origin_location_id' => $record->origin->location_id,
//            'origin_address' => $record->origin->address,
//            'origin_code' => $record->origin->code,
//            'delivery_location_id' => $record->delivery->location_id,
//            'delivery_address' => $record->delivery->address,
//            'delivery_code' => $record->delivery->code,
            'driver_identity_document_type_id' => optional($record->driver)->identity_document_type_id,
            'driver_number' => optional($record->driver)->number,
            'driver_names' => optional($record->driver)->name,
            'driver_lastnames' => optional($record->driver)->name,
            'driver_license' => optional($record->driver)->license,
            'transport_plate_number' => $record->transport_data ? $record->transport_data['plate_number'] : null,
            'transport_tuc' => $record->transport_data ? $record->transport_data['tuc'] : null,
//            'dispatcher_identity_document_type_id' => optional($record->dispatcher)->identity_document_type_id,
//            'dispatcher_number' => optional($record->dispatcher)->number,
//            'dispatcher_name' => optional($record->dispatcher)->name,
//            'dispatcher_number_mtc' => optional($record->dispatcher)->number_mtc,
            'items' => $items,
            'secondary_transports' => $record->secondary_transports,
            'secondary_drivers' => $record->secondary_drivers,
            'reference_documents' => $record->reference_documents,
        ];
    }
}
