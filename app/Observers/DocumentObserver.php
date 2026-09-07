<?php

namespace App\Observers;

use App\CoreFacturalo\Requests\Inputs\Functions;
use App\Models\Tenant\Cash;
use App\Models\Tenant\CashDocument;
use App\Models\Tenant\Company;
use App\Models\Tenant\Document;
use App\Models\Tenant\Series;
use Modules\Finance\Traits\FinanceTrait;
use Modules\Webhook\Services\WebhookDispatcher;
use Modules\Webhook\Services\WebhookEvents;

class DocumentObserver
{
    use FinanceTrait;
    /**
     * Handle the document "creating" event.
     *
     * @param  \App\Models\Tenant\Document  $document
     * @return void
     */
    public function creating(Document $document)
    {
        // El correlativo de una serie asignada a una máquina VendeYa es
        // autoridad LOCAL de esa máquina: usarla online garantiza colisión.
        // El bloqueo es POR SERIE — el resto de series del establecimiento
        // emite online con normalidad. Aplica también a máquinas revocadas
        // cuyas series aún no se liberan (su cola pendiente puede llegar al
        // reconectar); "Liberar series" en el panel las devuelve al online.
        // Los lotes del propio canal entran con el bypass registrado.
        if (!app()->bound('sync.batch.bypass')
            && class_exists(\Modules\Sync\Models\OfflineMachine::class)
            && \Illuminate\Support\Facades\Schema::connection('tenant')->hasTable('offline_machines')
        ) {
            $serieTakenByMachine = \App\Models\Tenant\Series::where('establishment_id', $document->establishment_id)
                ->where('document_type_id', $document->document_type_id)
                ->where('number', $document->series)
                ->whereNotNull('series_device_group_id')
                ->whereIn(
                    'series_device_group_id',
                    \Modules\Sync\Models\OfflineMachine::query()->select('series_device_group_id')
                )
                ->exists();

            if ($serieTakenByMachine) {
                throw new \Exception(
                    "La serie {$document->series} está asignada a una máquina VendeYa (conexión offline): " .
                    'usa otra serie del establecimiento o libérala desde el panel de Conexión Offline.'
                );
            }
        }

        $company = Company::active();

        // Serializa la asignaci├│n de correlativo por serie (evita duplicados en pagos concurrentes).
        Series::where('document_type_id', $document->document_type_id)
            ->where('number', $document->series)
            ->lockForUpdate()
            ->first();

        $seed = $document->number;
        $number = Functions::newNumber(
            $document->soap_type_id,
            $document->document_type_id,
            $document->series,
            $seed,
            Document::class
        );

        // Si el n├║mero ya est├í tomado (carrera residual), buscar el siguiente libre.
        if ($seed === '#' || $seed === null || $seed === '') {
            while (
                Document::where('document_type_id', $document->document_type_id)
                    ->where('series', $document->series)
                    ->where('number', $number)
                    ->exists()
            ) {
                $number++;
            }
        }

        $document->number = $number;
        $document->filename = Functions::filename($company, $document->document_type_id, $document->series, $number);
        $document->unique_filename = $document->filename; //campo ├║nico para evitar duplicados
    }

    /**
     * Handle the document "updated" event.
     *
     * @param  \App\Models\Tenant\Document  $document
     * @return void
     */
    public function updated(Document $document)
    {
        // Notifica el ciclo de vida SUNAT (aceptado/observado/rechazado/anulado)
        if ($document->wasChanged('state_type_id')) {
            if ($event = WebhookEvents::forDocumentState($document->state_type_id)) {
                app(WebhookDispatcher::class)->dispatch($event, $document);
            }
        }
    }

    /**
     * Handle the document "deleted" event.
     *
     * @param  \App\Models\Tenant\Document  $document
     * @return void
     */
    public function deleted(Document $document)
    {
        //
    }

    /**
     * Handle the document "restored" event.
     *
     * @param  \App\Models\Tenant\Document  $document
     * @return void
     */
    public function restored(Document $document)
    {
        //
    }

    /**
     * Handle the document "force deleted" event.
     *
     * @param  \App\Models\Tenant\Document  $document
     * @return void
     */
    public function forceDeleted(Document $document)
    {
        //
    }

    public function created(Document $document)
    {
        app(WebhookDispatcher::class)->dispatch(WebhookEvents::DOCUMENT_CREATED, $document);

        // Emisi├│n desde tienda / API sin caja abierta: emitir igual, sin asociar caja.
        $cash = Cash::where([
            ['user_id', auth()->id() ?: $document->user_id],
            ['state', true],
        ])->first();

        // Reintentos del canal offline (bandeja): la caja del turno original ya
        // cerró — el documento se asocia a la última caja del usuario.
        if (!$cash && app()->bound('sync.batch.bypass')) {
            $cash = Cash::where('user_id', auth()->id())->latest('id')->first();
        }

        if (!$cash) {
            throw (new \Illuminate\Database\Eloquent\ModelNotFoundException())->setModel(Cash::class);
        }

        $cash_document = CashDocument::where('cash_id', $cash->id)
                    ->where('document_id', $document->id)->first();
        if (!$cash_document) {
            $this->finance_cash_document( $document, $document->id );
        }
    }
}
