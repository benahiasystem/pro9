<?php
// ######## INICIO MODALIDAD DE EMISIÓN FISCAL ########

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
        $company = Company::active();

        // ######## INICIO NUMERACIÓN FISCAL VENEZUELA ########
        if ($document->fiscalReservationId() !== null) {
            $document->filename = Functions::filename($company, $document->document_type_id, $document->series, $document->number);
            $document->unique_filename = $document->filename;
            return;
        }
        // ######## FIN NUMERACIÓN FISCAL VENEZUELA ########

        // Serializa la asignaci├│n de correlativo por serie (evita duplicados en pagos concurrentes).
        Series::where('document_type_id', $document->document_type_id)
            ->where('number', $document->series)
            ->lockForUpdate()
            ->first();

        $seed = $document->number;
        $number = Functions::newNumber(
            $document->fiscal_environment,
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
        // Notifica cambios del ciclo de vida local que tengan un evento registrado.
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

        if (! $cash) {
            return;
        }

        $cash_document = CashDocument::where('cash_id', $cash->id)
                    ->where('document_id', $document->id)->first();
        if (!$cash_document) {
            $this->finance_cash_document( $document, $document->id );
        }
    }
}
// ######## FIN MODALIDAD DE EMISIÓN FISCAL ########
