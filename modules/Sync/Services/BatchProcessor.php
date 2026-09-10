<?php

namespace Modules\Sync\Services;

use App\CoreFacturalo\Facturalo;
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
        ];

        try {
            $user = $this->resolveMachineUser($machine, $event['user_id']);
            Auth::shouldUse('api');
            Auth::guard('api')->setUser($user);

            switch ($event['type']) {
                case 'cash_open':
                    $cash = $this->processCashOpen($user, $event);
                    $saved = SyncEvent::create($record + ['status' => 'accepted', 'cash_id' => $cash->id]);
                    break;

                case 'cash_close':
                    $cash = $this->processCashClose($user, $event);
                    $saved = SyncEvent::create($record + ['status' => 'accepted', 'cash_id' => optional($cash)->id]);
                    break;

                case 'sale':
                    $document = $this->processSale($machine, $event);
                    $saved = SyncEvent::create($record + ['status' => 'accepted', 'document_id' => $document->id]);
                    $saved->message = "{$document->series}-{$document->number}";
                    $saved->save();
                    break;

                case 'sale_note':
                    $saleNote = $this->processSaleNote($machine, $event);
                    $saved = SyncEvent::create($record + ['status' => 'accepted']);
                    $saved->message = $saleNote['number_full'];
                    $saved->save();
                    break;

                case 'void':
                    // La anulación se registra localmente. El comando
                    // sync:process-voids conserva compatibilidad con eventos
                    // pendientes creados antes de esta adaptación.
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

        $user = $this->resolveMachineUser($machine, $userId);
        Auth::shouldUse('api');
        Auth::guard('api')->setUser($user);

        // Las ventas del reintento tampoco deben chocar con el bloqueo online.
        app()->instance('sync.batch.bypass', true);

        try {
            switch ($stored->type) {
                case 'sale':
                    $document = $this->processSale($machine, [
                        'external_id' => $stored->external_id,
                        'payload' => $stored->payload,
                    ]);
                    $stored->status = 'accepted';
                    $stored->document_id = $document->id;
                    $stored->message = "{$document->series}-{$document->number}";
                    break;

                case 'sale_note':
                    $saleNote = $this->processSaleNote($machine, ['payload' => $stored->payload]);
                    $stored->status = 'accepted';
                    $stored->message = $saleNote['number_full'];
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
                    $stored->cash_id = optional($cash)->id;
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

    private function resolveMachineUser(OfflineMachine $machine, int $userId): User
    {
        return User::whereKey($userId)
            ->where('establishment_id', $machine->establishment_id)
            ->whereIn('type', ['seller', 'admin'])
            ->firstOrFail();
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

        // ########## INICIO CAMBIO OPERACIÓN FISCAL LOCAL
        $fact = DB::connection('tenant')->transaction(function () use ($inputs) {
            $facturalo = new Facturalo();
            $facturalo->save($inputs);
            $facturalo->updateQr();
            $facturalo->createPdf();
            $facturalo->senderXmlSignedBill();

            return $facturalo;
        });
        // ######### FIN CAMBIO OPERACIÓN FISCAL LOCAL

        return $fact->getDocument();
    }

    /**
     * Nota de venta offline: invoca la MISMA cadena del API público
     * (Tenant\Api\SaleNoteController@store — kardex, caja y PDF incluidos).
     * La serie 80 es dedicada de la máquina y el número viene pre-tomado del
     * ticket impreso: getDataSeries lo respeta bajo sync.batch.bypass.
     */
    public function processSaleNote(OfflineMachine $machine, array $event): array
    {
        $payload = $event['payload'];

        $serie = Series::find($payload['series_id'] ?? null);
        if (
            !$serie
            || $serie->document_type_id !== '80'
            || (int) $serie->series_device_group_id !== (int) $machine->series_device_group_id
        ) {
            throw new \Exception('La serie de nota de venta no pertenece a esta máquina');
        }
        if (empty($payload['number'])) {
            throw new \Exception('La nota de venta debe incluir su número local');
        }

        $request = new \Illuminate\Http\Request();
        $request->replace($payload);

        $controller = app(\App\Http\Controllers\Tenant\Api\SaleNoteController::class);
        $response = $controller->store($request);
        $result = is_array($response) ? $response : $response->getData(true);

        if (empty($result['success'])) {
            throw new \Exception('Nota de venta rechazada: ' . ($result['message'] ?? 'error desconocido'));
        }

        $numberFull = $result['data']['number'] ?? '';
        $expected = "{$serie->number}-{$payload['number']}";
        if ($numberFull !== $expected) {
            throw new \Exception("La nota se registró como {$numberFull} pero el ticket dice {$expected}: revisar en el facturador");
        }

        // Mismo paso que el POS online tras crear la nota: vincularla a la
        // caja del usuario (cash_open del lote garantiza una abierta).
        $saleNoteId = $result['data']['id'] ?? null;
        $cash = Cash::where([['user_id', Auth::id()], ['state', true]])->first()
            ?? Cash::where('user_id', Auth::id())->latest('id')->first();
        if ($cash && $saleNoteId) {
            \App\Models\Tenant\CashDocument::firstOrCreate([
                'cash_id' => $cash->id,
                'sale_note_id' => $saleNoteId,
            ]);
        }

        return ['number_full' => $numberFull, 'id' => $saleNoteId];
    }

    /**
     * Réplica exacta de Functions::newNumber (caso '#') para informar el
     * contador inicial de una serie al enrolar.
     */
    public static function nextNumberFor(string $document_type_id, string $serie): int
    {
        // Notas de venta: el correlativo vive en sale_notes (misma regla que
        // getDataSeries del API: último de la serie + 1).
        if ($document_type_id === '80') {
            $last = \App\Models\Tenant\SaleNote::select('number')
                ->where('series', $serie)
                ->orderBy('number', 'desc')
                ->first();

            return $last ? (int) $last->number + 1 : 1;
        }

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
