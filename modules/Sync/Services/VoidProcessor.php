<?php

namespace Modules\Sync\Services;

use App\Models\Tenant\Configuration;
use App\Models\Tenant\Document;
use App\Models\Tenant\Series;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Sync\Models\OfflineMachine;
use Modules\Sync\Models\SyncEvent;

/**
 * Registra localmente anulaciones de facturas recibidas desde una máquina
 * VendeYa. No genera XML/CDR ni transmite a una autoridad fiscal.
 */
class VoidProcessor
{
    /**
     * Intenta materializar la anulación descrita por el payload del evento.
     *
     * @return array{status: string, message: string, document_id?: int|null}
     */
    public function attempt(OfflineMachine $machine, array $payload): array
    {
        $document = $this->resolveDocument($machine, $payload);
        if (!$document) {
            return [
                'status' => 'error',
                'message' => 'No se encontró la venta original a anular',
            ];
        }

        if (in_array($document->state_type_id, ['11', '13'])) {
            return [
                'status' => 'accepted',
                'message' => "{$document->series}-{$document->number}: la anulación ya está registrada o en trámite",
                'document_id' => $document->id,
            ];
        }

        if ($error = $this->validateShippingWindow($document)) {
            return [
                'status' => 'error',
                'message' => $error,
                'document_id' => $document->id,
            ];
        }

        // ########## INICIO CAMBIO OPERACIÓN FISCAL LOCAL
        DB::connection('tenant')->transaction(function () use ($document) {
            $document->state_type_id = '11';
            $document->save();
        });
        // ######### FIN CAMBIO OPERACIÓN FISCAL LOCAL

        return [
            'status' => 'accepted',
            'message' => "{$document->series}-{$document->number}: anulación registrada localmente",
            'document_id' => $document->id,
        ];
    }

    /**
     * La venta original: primero por el evento sale ya registrado (vínculo
     * fuerte), como respaldo por serie+número validando que la serie sea de la
     * máquina.
     */
    private function resolveDocument(OfflineMachine $machine, array $payload): ?Document
    {
        if (!empty($payload['original_external_id'])) {
            $sale = SyncEvent::where('machine_id', $machine->id)
                ->where('external_id', $payload['original_external_id'])
                ->whereIn('type', ['sale', 'sale_note'])
                ->first();
            if ($sale && $sale->document_id) {
                return Document::find($sale->document_id);
            }
        }

        $serie = $payload['serie_documento'] ?? null;
        $number = $payload['numero_documento'] ?? null;
        if (!$serie || !$number) {
            return null;
        }

        $ownsSerie = Series::where('number', $serie)
            ->where('series_device_group_id', $machine->series_device_group_id)
            ->exists();
        if (!$ownsSerie) {
            return null;
        }

        return Document::where('series', $serie)->where('number', $number)->first();
    }

    /** Misma restricción configurable que la anulación manual del facturador. */
    private function validateShippingWindow(Document $document): ?string
    {
        $configuration = Configuration::select('restrict_voided_send', 'shipping_time_days_voided')->firstOrFail();

        if ($configuration->restrict_voided_send) {
            $difference_days = $configuration->shipping_time_days_voided
                - $document->getDiffInDaysDateOfIssue(Carbon::now());

            if ($difference_days < 0) {
                return "El documento excede los {$configuration->shipping_time_days_voided} días válidos para ser anulado.";
            }
        }

        return null;
    }

}
