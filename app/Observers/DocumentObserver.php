<?php

namespace App\Observers;

use App\CoreFacturalo\Requests\Inputs\Functions;
use App\Models\Tenant\Cash;
use App\Models\Tenant\CashDocument;
use App\Models\Tenant\Company;
use App\Models\Tenant\Document;
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
        // Con una conexión offline activa (máquina VendeYa enrolada), la
        // emisión online del establecimiento se bloquea para impedir
        // correlativos en conflicto. Los lotes de sincronización entran
        // con el bypass registrado por el propio canal.
        if (!app()->bound('sync.batch.bypass')
            && class_exists(\Modules\Sync\Models\OfflineMachine::class)
            && \Illuminate\Support\Facades\Schema::connection('tenant')->hasTable('offline_machines')
            && \Modules\Sync\Models\OfflineMachine::where('status', 'active')
                ->where('establishment_id', $document->establishment_id)
                ->exists()
        ) {
            throw new \Exception(
                'Este establecimiento tiene una conexión offline activa (VendeYa): ' .
                'la emisión online está bloqueada para evitar conflictos de numeración.'
            );
        }

        $company = Company::active();
        $number = Functions::newNumber($document->soap_type_id,
                                       $document->document_type_id,
                                       $document->series,
                                       $document->number, Document::class);
        $document->number = $number;

        $document->filename = Functions::filename($company, $document->document_type_id, $document->series, $number);
        $document->unique_filename = $document->filename; //campo único para evitar duplicados

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
        // Antes de la lógica de caja: Cash::firstOrFail() puede lanzar excepción
        app(WebhookDispatcher::class)->dispatch(WebhookEvents::DOCUMENT_CREATED, $document);

        // Esto nos verifica que es un documento generado desde el api
        $cash = Cash::where([
            ['user_id', auth()->id()],
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
