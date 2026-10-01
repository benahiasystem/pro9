<?php

namespace App\Services\Tenant;

use App\CoreFacturalo\Requests\Api\Transform\DocumentTransform;
use App\CoreFacturalo\Requests\Api\Validation\DocumentValidation;
use App\CoreFacturalo\Requests\Inputs\DocumentInput;
use App\Http\Controllers\Tenant\DocumentController;
use App\Http\Controllers\Tenant\SaleNoteController;
use App\Models\Tenant\Document;
use App\Models\Tenant\Establishment;
use App\Models\Tenant\Order;
use App\Models\Tenant\Series;
use App\Models\Tenant\StatusOrder;
use App\Models\Tenant\User;
use Illuminate\Support\Facades\Log;
use Modules\Sale\Helpers\SaleNoteHelper;
use Throwable;

/**
 * Genera el comprobante de un pedido cuando el estado aplicado
 * tiene action_generate_document (mismo flujo que el cambio manual en admin).
 */
class OrderDocumentFromStatusService
{
    /**
     * @return array{
     *     generated: bool,
     *     sale_note_id: int|null,
     *     sale_note_number_full: string|null,
     *     document_id: int|null,
     *     document_external_id: string|null,
     *     number_document: string|null,
     *     message: string|null,
     *     type: string|null
     * }
     */
    public function generateIfConfigured(Order $order, ?StatusOrder $status = null, bool $force = false): array
    {
        $empty = [
            'generated' => false,
            'sale_note_id' => null,
            'sale_note_number_full' => null,
            'document_id' => null,
            'document_external_id' => null,
            'number_document' => null,
            'message' => null,
            'type' => null,
        ];

        $status = $status ?: $order->payment_status_order;

        if (! $status || (! $force && ! $status->action_generate_document)) {
            return $empty;
        }

        $order->loadMissing('sale_note');

        $purchase = json_decode(json_encode($order->purchase), true) ?: [];
        // ########## INICIO CAMBIO FACTURAS Y NOTAS DE VENTA EN PEDIDOS
        $tipoDoc = $purchase['codigo_tipo_documento'] ?? '01';
        // ######### FIN CAMBIO FACTURAS Y NOTAS DE VENTA EN PEDIDOS

        $alreadyHasDocument = $tipoDoc == '80'
            ? (bool) $order->sale_note
            : (bool) $order->document_external_id;

        if ($alreadyHasDocument) {
            if ($tipoDoc == '80') {
                return array_merge($empty, [
                    'sale_note_id' => optional($order->sale_note)->id,
                    'sale_note_number_full' => optional($order->sale_note)->number_full,
                    'message' => 'El pedido ya tiene comprobante',
                    'type' => 'success',
                ]);
            }

            $documentId = Document::where('external_id', $order->document_external_id)->value('id');

            return array_merge($empty, [
                'document_id' => $documentId ? (int) $documentId : null,
                'document_external_id' => $order->document_external_id,
                'number_document' => $order->number_document,
                'message' => 'El pedido ya tiene comprobante',
                'type' => 'success',
            ]);
        }

        try {
            // ########## INICIO CAMBIO SOLO FACTURAS Y NOTAS DE VENTA
            if (! in_array($tipoDoc, ['01', '80'], true)) {
                return array_merge($empty, [
                    'message' => 'El pedido sólo puede generar Factura o Nota de venta',
                    'type' => 'warning',
                ]);
            }
            // ######### FIN CAMBIO SOLO FACTURAS Y NOTAS DE VENTA

            $emitterUser = User::query()
                ->whereNotNull('establishment_id')
                ->orderBy('id')
                ->first()
                ?: User::query()->orderBy('id')->first();

            $establishmentId = $purchase['establishment_id']
                ?? optional($emitterUser)->establishment_id
                ?? optional(Establishment::query()->orderBy('id')->first())->id;

            if (! $establishmentId) {
                Log::error('No hay establecimiento disponible para generar el comprobante del pedido '.$order->id);

                return array_merge($empty, [
                    'message' => 'No hay establecimiento disponible para generar el comprobante',
                    'type' => 'warning',
                ]);
            }

            $purchase['establishment_id'] = $establishmentId;

            $series = Series::where('establishment_id', $establishmentId)
                ->where('document_type_id', $tipoDoc)
                ->first();

            if (! $series) {
                Log::error('No hay series disponibles para generar el comprobante. Tipo doc: '.$tipoDoc);

                return array_merge($empty, [
                    'message' => 'No hay series disponibles para generar el comprobante',
                    'type' => 'warning',
                ]);
            }

            if ($tipoDoc == '80') {
                $purchase['serie_documento'] = $series->id;
                $saleNoteData = SaleNoteHelper::transformForOrder($purchase);
                $saleNoteData['series_id'] = $series->id;
                $saleNoteData['prefix'] = 'NV';
                $saleNoteData['order_id'] = $order->id;
                if (empty($saleNoteData['establishment_id'])) {
                    $saleNoteData['establishment_id'] = $establishmentId;
                }

                $response = app(SaleNoteController::class)->storeWithData($saleNoteData);

                if (! isset($response['success']) || ! $response['success']) {
                    Log::error('Error al generar la nota de venta autom├ítica: '.($response['message'] ?? ''));

                    return array_merge($empty, [
                        'message' => $response['message'] ?? 'No se pudo generar la nota de venta',
                        'type' => 'warning',
                    ]);
                }

                $saleNoteId = $response['data']['id'] ?? null;
                $saleNoteNumber = $response['data']['number_full']
                    ?? optional($order->fresh('sale_note')->sale_note)->number_full;

                return array_merge($empty, [
                    'generated' => true,
                    'sale_note_id' => $saleNoteId,
                    'sale_note_number_full' => $saleNoteNumber,
                    'message' => 'Nota de venta generada exitosamente',
                    'type' => 'success',
                ]);
            }

            // Mismo flujo que el panel web (DocumentController::storeWithData):
            // Generar el documento local y su PDF sin demorar la interfaz con el correo.
            $purchase['serie_documento'] = $series->number;

            $inputs = DocumentTransform::transform($purchase);
            $inputs['establishment_id'] = $establishmentId;
            $inputs = DocumentValidation::validation($inputs);
            $inputs = DocumentInput::set($inputs);

            // Tienda / invitado: auth()->id() puede ser null; Facturalo exige usuario emisor.
            if (empty($inputs['user_id'])) {
                $inputs['user_id'] = optional($emitterUser)->id
                    ?? User::query()->orderBy('id')->value('id');
            }

            $response = app(DocumentController::class)->storeWithData($inputs);

            if (! isset($response['success']) || ! $response['success']) {
                Log::error('Error al generar el comprobante autom├ítico: '.($response['message'] ?? ''));

                return array_merge($empty, [
                    'message' => $response['message'] ?? 'No se pudo generar el comprobante',
                    'type' => 'warning',
                ]);
            }

            $documentId = $response['data']['id'] ?? null;
            $document = $documentId ? Document::find($documentId) : null;

            if (! $document) {
                return array_merge($empty, [
                    'message' => 'Comprobante creado pero no se pudo leer el documento',
                    'type' => 'warning',
                ]);
            }

            $order->update([
                'document_external_id' => $document->external_id,
                'number_document' => $document->number_full,
            ]);

            return array_merge($empty, [
                'generated' => true,
                'document_id' => (int) $document->id,
                'document_external_id' => $document->external_id,
                'number_document' => $document->number_full,
                'message' => 'Comprobante generado exitosamente',
                'type' => 'success',
            ]);
        } catch (Throwable $e) {
            Log::error('Excepci├│n al generar comprobante autom├ítico: '.$e->getMessage());
            Log::error($e->getTraceAsString());

            return array_merge($empty, [
                'message' => $e->getMessage(),
                'type' => 'warning',
            ]);
        }
    }

    /**
     * Tras un pago exitoso de pasarela (Culqi / MP / Izipay),
     * dispara la misma generaci├│n de comprobante que el cambio manual de estado.
     */
    public function afterGatewayPaymentCompleted(Order $order): array
    {
        $order->loadMissing(['sale_note', 'payment_status_order']);
        $status = $order->payment_status_order;

        if (! $status && $order->payment_status_order_id) {
            $status = StatusOrder::find($order->payment_status_order_id);
        }

        if (! $status) {
            $paidId = StatusOrder::resolvePaidPaymentStatusId();
            $status = $paidId ? StatusOrder::find($paidId) : null;
        }

        // Cobro exitoso de pasarela: emitir comprobante (misma l├│gica que el cambio manual en admin).
        return $this->generateIfConfigured($order, $status, true);
    }
}
