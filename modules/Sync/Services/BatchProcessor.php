<?php

namespace Modules\Sync\Services;

use App\CoreFacturalo\Facturalo;
use App\CoreFacturalo\Helpers\Storage\StorageDocument;
use App\CoreFacturalo\Requests\Api\Transform\DocumentTransform;
use App\CoreFacturalo\Requests\Api\Validation\DocumentValidation;
use App\CoreFacturalo\Requests\Inputs\DocumentInput;
use App\Models\Tenant\Cash;
use App\Models\Tenant\Document;
use App\Models\Tenant\Series;
use App\Models\Tenant\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Modules\Document\Models\SeriesConfiguration;
use Modules\Sync\Models\OfflineMachine;
use Modules\Sync\Models\SyncEvent;

/**
 * Motor del lote cronológico offline: procesa cada evento (apertura/venta/
 * anulación/cierre) y permite REINTENTAR desde la bandeja un evento con error
 * o pendiente, con la misma lógica exacta del canal batch.
 */
class BatchProcessor
{
    use StorageDocument;

    public function processEvent(OfflineMachine $machine, array $event): array
    {
        $record = [
            'machine_id' => $machine->id,
            'user_id' => $event['user_id'] ?? null,
            'seq' => $event['seq'],
            'type' => $event['type'],
            'external_id' => $event['external_id'],
            'occurred_at' => $event['occurred_at'],
            'payload' => $event['payload'],
            'hash' => $event['hash'] ?? null,
            'xml_unsigned' => $event['xml_unsigned'] ?? null,
        ];

        try {
            $user = User::findOrFail($event['user_id']);
            Auth::shouldUse('api');
            Auth::guard('api')->setUser($user);

            switch ($event['type']) {
                case 'cash_open':
                    $cash = $this->processCashOpen($user, $event);
                    $saved = SyncEvent::create($record + ['status' => 'accepted', 'cash_id' => $cash->id]);
                    break;

                case 'cash_close':
                    $cash = $this->processCashClose($user, $event);
                    $saved = SyncEvent::create($record + ['status' => 'accepted', 'cash_id' => $cash?->id]);
                    break;

                case 'sale':
                    $document = $this->processSale($machine, $event);
                    $saved = SyncEvent::create($record + ['status' => 'accepted', 'document_id' => $document->id]);
                    $saved->message = "{$document->series}-{$document->number}";
                    $saved->save();
                    break;

                case 'void':
                    // Si la venta ya está aceptada, la baja/resumen de
                    // anulación se genera aquí mismo; si no, queda pending y
                    // la retoma el comando sync:process-voids.
                    $outcome = (new VoidProcessor())->attempt($machine, $event['payload']);
                    $saved = SyncEvent::create($record + [
                        'status' => $outcome['status'],
                        'message' => $outcome['message'],
                        'document_id' => $outcome['document_id'] ?? null,
                    ]);
                    break;

                default:
                    throw new \Exception("Tipo de evento no soportado: {$event['type']}");
            }

            return $saved->toResult();
        } catch (\Throwable $e) {
            $saved = SyncEvent::create($record + [
                'status' => 'error',
                'message' => mb_substr($e->getMessage(), 0, 900),
            ]);

            return $saved->toResult();
        }
    }

    /**
     * Reintento desde la bandeja: mismo evento, misma lógica. Solo aplica a
     * errores (venta/caja) y anulaciones pendientes o con error.
     */
    public function retry(SyncEvent $stored): SyncEvent
    {
        $machine = $stored->machine;
        $userId = $stored->user_id ?? $machine->user_id;

        $user = User::findOrFail($userId);
        Auth::shouldUse('api');
        Auth::guard('api')->setUser($user);

        // Las ventas del reintento tampoco deben chocar con el bloqueo online.
        app()->instance('sync.batch.bypass', true);

        try {
            switch ($stored->type) {
                case 'sale':
                    if (!$stored->xml_unsigned) {
                        throw new \Exception('Sin XML almacenado: este evento es anterior a la bandeja y no puede reintentarse');
                    }
                    $document = $this->processSale($machine, [
                        'external_id' => $stored->external_id,
                        'payload' => $stored->payload,
                        'hash' => $stored->hash,
                        'xml_unsigned' => $stored->xml_unsigned,
                    ]);
                    $stored->status = 'accepted';
                    $stored->document_id = $document->id;
                    $stored->message = "{$document->series}-{$document->number}";
                    break;

                case 'void':
                    $outcome = (new VoidProcessor())->attempt($machine, $stored->payload);
                    $stored->status = $outcome['status'];
                    $stored->message = $outcome['message'];
                    $stored->document_id = $outcome['document_id'] ?? $stored->document_id;
                    break;

                case 'cash_open':
                    $cash = $this->processCashOpen($user, ['occurred_at' => $stored->occurred_at, 'payload' => $stored->payload]);
                    $stored->status = 'accepted';
                    $stored->cash_id = $cash->id;
                    $stored->message = null;
                    break;

                case 'cash_close':
                    $cash = $this->processCashClose($user, ['occurred_at' => $stored->occurred_at, 'payload' => $stored->payload]);
                    $stored->status = 'accepted';
                    $stored->cash_id = $cash?->id;
                    $stored->message = null;
                    break;

                default:
                    throw new \Exception("Tipo de evento no soportado: {$stored->type}");
            }
        } catch (\Throwable $e) {
            $stored->status = 'error';
            $stored->message = mb_substr($e->getMessage(), 0, 900);
        } finally {
            app()->forgetInstance('sync.batch.bypass');
        }

        $stored->save();

        return $stored;
    }

    public function processCashOpen(User $user, array $event): Cash
    {
        $open = Cash::where([['user_id', $user->id], ['state', true]])->first();
        if ($open) {
            return $open;
        }

        $occurred = \Carbon\Carbon::parse($event['occurred_at']);

        return Cash::create([
            'user_id' => $user->id,
            'date_opening' => $occurred->format('Y-m-d'),
            'time_opening' => $occurred->format('H:i:s'),
            'beginning_balance' => $event['payload']['beginning_balance'] ?? 0,
            'state' => true,
        ]);
    }

    public function processCashClose(User $user, array $event): ?Cash
    {
        $cash = Cash::where([['user_id', $user->id], ['state', true]])->first();
        if (!$cash) {
            return null;
        }

        $occurred = \Carbon\Carbon::parse($event['occurred_at']);
        $cash->date_closed = $occurred->format('Y-m-d');
        $cash->time_closed = $occurred->format('H:i:s');
        $cash->final_balance = $event['payload']['final_balance'] ?? $cash->final_balance;
        $cash->state = false;
        $cash->save();

        return $cash;
    }

    public function processSale(OfflineMachine $machine, array $event): Document
    {
        if (empty($event['xml_unsigned']) || empty($event['hash'])) {
            throw new \Exception('La venta debe incluir xml_unsigned y hash');
        }

        $payload = $event['payload'];

        // La numeración pertenece a la máquina: la serie debe ser de su grupo.
        $serie = $payload['serie_documento'] ?? null;
        $ownsSerie = Series::where('number', $serie)
            ->where('series_device_group_id', $machine->series_device_group_id)
            ->exists();
        if (!$ownsSerie) {
            throw new \Exception("La serie {$serie} no pertenece a esta máquina");
        }

        // Misma cadena que la API pública: transform → validation → set.
        $inputs = DocumentTransform::transform($payload);
        $inputs = DocumentValidation::validation($inputs);
        $inputs = DocumentInput::set($inputs, 'api');
        $inputs['external_id'] = $event['external_id'];

        $clientXml = base64_decode($event['xml_unsigned']);

        $fact = DB::connection('tenant')->transaction(function () use ($inputs, $clientXml, $event) {
            $facturalo = new Facturalo();
            $facturalo->save($inputs);
            $facturalo->createXmlUnsigned();

            // Test de contrato vivo: el XML de la máquina debe ser BYTE-idéntico
            // al que generan las plantillas del facturador para el mismo payload.
            $document = $facturalo->getDocument();
            $serverXml = $this->getStorage($document->filename, 'unsigned');
            if ($serverXml !== $clientXml) {
                throw new \Exception('El XML de la máquina no coincide con el del facturador (paridad rota)');
            }

            $facturalo->signXmlUnsigned();
            $facturalo->updateHash();
            $facturalo->updateQr();

            $document->refresh();
            if ($document->hash !== $event['hash']) {
                throw new \Exception("El hash firmado ({$document->hash}) no coincide con el del ticket ({$event['hash']})");
            }

            $facturalo->createPdf();

            return $facturalo;
        });

        return $fact->getDocument();
    }

    /**
     * Réplica exacta de Functions::newNumber (caso '#') para informar el
     * contador inicial de una serie al enrolar.
     */
    public static function nextNumberFor(string $document_type_id, string $serie): int
    {
        $document = Document::select('number')
            ->where('document_type_id', $document_type_id)
            ->where('series', $serie)
            ->orderBy('number', 'desc')
            ->first();

        if ($document) {
            return (int) $document->number + 1;
        }

        $configuration = SeriesConfiguration::where([
            ['document_type_id', $document_type_id],
            ['series', $serie],
        ])->first();

        return $configuration ? (int) $configuration->number : 1;
    }
}
