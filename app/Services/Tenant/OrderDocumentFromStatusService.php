<?php

namespace App\Services\Tenant;

use App\Http\Controllers\Tenant\Api\DocumentController;
use App\Http\Controllers\Tenant\SaleNoteController;
use App\Models\Tenant\Establishment;
use App\Models\Tenant\Order;
use App\Models\Tenant\Series;
use App\Models\Tenant\StatusOrder;
use Illuminate\Http\Request;
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
     * @return array{generated: bool, sale_note_id: int|null, message: string|null, type: string|null}
     */
    public function generateIfConfigured(Order $order, ?StatusOrder $status = null, bool $force = false): array
    {
        $status = $status ?: $order->payment_status_order;

        if (! $status || (! $force && ! $status->action_generate_document)) {
            return [
                'generated' => false,
                'sale_note_id' => null,
                'message' => null,
                'type' => null,
            ];
        }

        $order->loadMissing('sale_note');

        $purchase = json_decode(json_encode($order->purchase), true) ?: [];
        $tipoDoc = $purchase['codigo_tipo_documento'] ?? '03';

        $alreadyHasDocument = $tipoDoc == '80'
            ? (bool) $order->sale_note
            : (bool) $order->document_external_id;

        if ($alreadyHasDocument) {
            return [
                'generated' => false,
                'sale_note_id' => null,
                'message' => 'El pedido ya tiene comprobante',
                'type' => 'success',
            ];
        }

        try {
            $establishmentId = $purchase['establishment_id'] ?? optional(Establishment::first())->id;
            $series = Series::where('establishment_id', $establishmentId)
                ->where('document_type_id', $tipoDoc)
                ->first();

            if (! $series) {
                Log::error('No hay series disponibles para generar el comprobante. Tipo doc: '.$tipoDoc);

                return [
                    'generated' => false,
                    'sale_note_id' => null,
                    'message' => 'No hay series disponibles para generar el comprobante',
                    'type' => 'warning',
                ];
            }

            if ($tipoDoc == '80') {
                $purchase['serie_documento'] = $series->id;
                $saleNoteData = SaleNoteHelper::transformForOrder($purchase);
                $saleNoteData['series_id'] = $series->id;
                $saleNoteData['prefix'] = 'NV';
                $saleNoteData['order_id'] = $order->id;

                $response = app(SaleNoteController::class)->storeWithData($saleNoteData);

                if (! isset($response['success']) || ! $response['success']) {
                    Log::error('Error al generar la nota de venta automática: '.($response['message'] ?? ''));

                    return [
                        'generated' => false,
                        'sale_note_id' => null,
                        'message' => $response['message'] ?? 'No se pudo generar la nota de venta',
                        'type' => 'warning',
                    ];
                }

                return [
                    'generated' => true,
                    'sale_note_id' => $response['data']['id'] ?? null,
                    'message' => 'Nota de venta generada exitosamente',
                    'type' => 'success',
                ];
            }

            $purchase['serie_documento'] = $series->id;
            $requestDocument = new Request();
            $requestDocument->replace($purchase);

            $response = app(DocumentController::class)->store($requestDocument);

            if (isset($response['success']) && $response['success']) {
                $order->update([
                    'document_external_id' => $response['data']['external_id'],
                    'number_document' => $response['data']['number'],
                ]);

                return [
                    'generated' => true,
                    'sale_note_id' => null,
                    'message' => 'Comprobante generado exitosamente',
                    'type' => 'success',
                ];
            }

            Log::error('Error al generar el comprobante automático: '.($response['message'] ?? ''));

            return [
                'generated' => false,
                'sale_note_id' => null,
                'message' => $response['message'] ?? 'No se pudo generar el comprobante',
                'type' => 'warning',
            ];
        } catch (Throwable $e) {
            Log::error('Excepción al generar comprobante automático: '.$e->getMessage());
            Log::error($e->getTraceAsString());

            return [
                'generated' => false,
                'sale_note_id' => null,
                'message' => $e->getMessage(),
                'type' => 'warning',
            ];
        }
    }

    /**
     * Tras un pago exitoso de pasarela (Culqi / MP / Izipay),
     * dispara la misma generación de comprobante que el cambio manual de estado.
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

        // Cobro exitoso de pasarela: emitir comprobante (misma lógica que el cambio manual en admin).
        return $this->generateIfConfigured($order, $status, true);
    }
}
