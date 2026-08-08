<?php

namespace Modules\Sync\Services;

use App\CoreFacturalo\Facturalo;
use App\CoreFacturalo\Requests\Inputs\SummaryInput;
use App\CoreFacturalo\Requests\Inputs\VoidedInput;
use App\CoreFacturalo\Requests\Web\Validation\SummaryValidation;
use App\CoreFacturalo\Requests\Web\Validation\VoidedValidation;
use App\Models\Tenant\Configuration;
use App\Models\Tenant\Document;
use App\Models\Tenant\Series;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Sync\Models\OfflineMachine;
use Modules\Sync\Models\SyncEvent;

/**
 * Materializa las anulaciones offline. El evento void de la máquina es solo
 * datos (qué venta y por qué); el documento de anulación real se genera AQUÍ
 * con la misma cadena que usan los botones del facturador — factura (grupo
 * 01): Comunicación de Baja; boleta (grupo 02): resumen de anulación
 * (summary_status_type_id '3') — porque su correlativo (RA/RC por día) es
 * global del tenant y su fecha es la del día de emisión: no puede nacer en la
 * máquina.
 *
 * Regla SUNAT: solo se puede dar de baja un documento ACEPTADO (la boleta,
 * además, informada en un RC aceptado previo). Mientras no lo esté, el evento
 * espera en pending y lo retoma sync:process-voids.
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

        if ($document->state_type_id !== '05') {
            return [
                'status' => 'pending',
                'message' => "En espera: {$document->series}-{$document->number} aún no está aceptado por SUNAT",
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

        if (Facturalo::validateCertificate()) {
            return [
                'status' => 'pending',
                'message' => 'Certificado digital no encontrado: la anulación queda en espera',
                'document_id' => $document->id,
            ];
        }

        $reason = trim((string) ($payload['motivo'] ?? '')) ?: 'ERROR EN EMISIÓN';
        $identifier = $this->materialize($document, $reason);

        return [
            'status' => 'accepted',
            'message' => "Anulación {$identifier} generada y enviada a SUNAT",
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
            $sale = SyncEvent::where('external_id', $payload['original_external_id'])->first();
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

    /**
     * Genera y envía el documento de anulación (misma secuencia que
     * VoidedController@store / SummaryTrait@save). El estado del ticket lo
     * siguen los flujos existentes de la sección Anulaciones.
     */
    private function materialize(Document $document, string $reason): string
    {
        $inputs = [
            'date_of_reference' => $document->date_of_issue->format('Y-m-d'),
            'summary_status_type_id' => '3',
            'documents' => [[
                'document_id' => $document->id,
                'description' => $reason,
            ]],
        ];

        if ($document->group_id === '01') {
            $inputs = VoidedValidation::validation($inputs);
            $inputs = VoidedInput::set($inputs);
        } else {
            $inputs = SummaryValidation::validation($inputs);
            $inputs = SummaryInput::set($inputs);
        }

        $fact = DB::connection('tenant')->transaction(function () use ($inputs) {
            $facturalo = new Facturalo();
            $facturalo->save($inputs);
            $facturalo->createXmlUnsigned();
            $service_pse_xml = $facturalo->servicePseSendXml();
            $facturalo->signXmlUnsigned($service_pse_xml['xml_signed']);
            $facturalo->senderXmlSignedSummary();

            return $facturalo;
        });

        $voidDocument = $fact->getDocument();

        // Segundo paso del ciclo: consultar el ticket para que el documento
        // pase de "Por anular" a "Anulado". Si SUNAT aún no resuelve, la
        // consulta puede repetirse desde la sección Anulaciones.
        if ($voidDocument->ticket) {
            try {
                sleep(3);
                $query = new Facturalo();
                $query->setDocument($voidDocument);
                $query->setType($document->group_id === '01' ? 'voided' : 'summary');
                $query->statusSummary($voidDocument->ticket);
            } catch (\Throwable $e) {
                // El ticket sigue consultable después; la anulación ya existe.
            }
        }

        return $voidDocument->identifier;
    }
}
