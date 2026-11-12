<?php

namespace App\Services\Sellnow;

use App\CoreFacturalo\Facturalo;
use App\Http\Controllers\Tenant\Api\SaleNoteController;
use App\Models\Tenant\{Document, SaleNote, SellnowSale};
use Illuminate\Support\Facades\DB;

/** Durable effect claims. An uncertain external delivery is never sent twice automatically. */
class SaleEffects
{
    public function run(SellnowSale $sale): void
    {
        if ($sale->status !== 'confirmed') return;
        foreach (['pdf','email','print'] as $effect) {
            if ($effect === 'print' && ($sale->fresh()->effects['pdf']['status'] ?? '') !== 'confirmed') continue;
            if (!$this->claim($sale, $effect)) continue;
            try {
                if ($effect === 'print') {
                    // Queue creation and its persisted acknowledgement share a
                    // database transaction. A lost HTTP cannot enqueue it twice.
                    DB::connection('tenant')->transaction(function () use ($sale, $effect) {
                        $locked = SellnowSale::query()->whereKey($sale->id)->lockForUpdate()->firstOrFail();
                        // A paused older claimant may resume after another worker
                        // recovered its lease. Recheck under the same lock that
                        // protects queue insertion and its acknowledgement.
                        if (($locked->effects['print']['status'] ?? '') === 'confirmed') return;
                        $print = $this->execute($locked, $effect);
                        $this->finish($sale, $effect, $print['reason'] === 'error' ? 'failed' : 'confirmed', $print);
                    });
                } else {
                    $this->execute($sale, $effect);
                    $this->finish($sale, $effect, 'confirmed');
                }
            } catch (\Throwable $exception) {
                // PDF rendering is idempotent and may be recovered. SMTP and
                // remote providers can have succeeded despite a transport error.
                $this->finish($sale, $effect, $effect === 'email' ? 'uncertain' : 'failed', null,
                    $effect === 'email' ? 'El resultado requiere verificación antes de reenviar.' : 'El recurso pendiente se recuperará sin crear otra venta.');
                try { report($exception); } catch (\Throwable $reportingFailure) { /* Keep the durable outcome authoritative. */ }
            }
        }
    }

    protected function execute(SellnowSale $sale, string $effect)
    {
        $actions = SaleOperations::actions($sale->envelope['payload']);
        if ($sale->kind === 'document') {
            $document = Document::findOrFail($sale->document_id);
            $fact = new Facturalo();
            $fact->setDocument($document);
            $fact->setType('invoice');
            $fact->setActions($actions);
            if ($effect === 'pdf') return $fact->createPdf($document, 'invoice', $actions['format_pdf']);
            if ($effect === 'email') return $fact->sendEmail();
            if (\App\Services\Fiscal\HkaPdf::applies($document) && in_array($actions['format_pdf'], ['a4','a5'], true)) {
                // PDF preparation already cached this authorized artifact outside
                // the transaction. Queue only its stored bytes; never download
                // from HKA while holding the print acknowledgement transaction.
                $pdf = app(\App\Services\Fiscal\HkaPdf::class)->stored($document, $actions['format_pdf']);
                $request = \Illuminate\Http\Request::create('/api/print-orders','POST',[
                    'name_printer'=>$actions['name_printer'],'client_public_ip'=>$actions['client_public_ip'],
                    'pdf_b64'=>base64_encode($pdf),
                ]);
                $response = app(\Modules\Restaurant\Http\Controllers\PrintOrderController::class)->store($request);
                $body = $response->getData(true);
                return ['auto_printed'=>$response->getStatusCode() === 201,
                    'print_order_id'=>$body['id'] ?? null,
                    'reason'=>$response->getStatusCode() === 201 ? null : ($response->getStatusCode() === 403 ? 'ip_mismatch' : 'no_printer')];
            }
            return $fact->generatePrintOrder();
        }
        $note = SaleNote::findOrFail($sale->sale_note_id);
        $controller = app(SaleNoteController::class);
        if ($effect === 'pdf') return $controller->createPdf($note, $actions['format_pdf'], $note->filename);
        if ($effect === 'print') return $controller->printStoredSale($note, $actions);
        return null;
    }

    protected function claim(SellnowSale $sale, string $effect): bool
    {
        return DB::connection('tenant')->transaction(function () use ($sale, $effect) {
            $locked = SellnowSale::query()->whereKey($sale->id)->lockForUpdate()->firstOrFail();
            $effects = $locked->effects ?? [];
            $state = $effects[$effect]['status'] ?? 'disabled';
            if ($state === 'processing') {
                if (time() - ($effects[$effect]['started_at'] ?? time()) < 120) return false;
                if ($effect === 'email') {
                    $effects[$effect]['status'] = 'uncertain';
                    $locked->update(['effects'=>$effects]);
                    return false;
                }
            } elseif (!in_array($state, ['pending','failed'], true)) {
                return false;
            }
            $effects[$effect] = ['status'=>'processing','started_at'=>time()];
            $locked->update(['effects'=>$effects]);
            return true;
        });
    }

    private function finish(SellnowSale $sale, string $effect, string $status, ?array $print = null, ?string $message = null): void
    {
        DB::connection('tenant')->transaction(function () use ($sale, $effect, $status, $print, $message) {
            $locked = SellnowSale::query()->whereKey($sale->id)->lockForUpdate()->firstOrFail();
            $effects = $locked->effects;
            if (($effects[$effect]['status'] ?? '') === 'confirmed' && $status !== 'confirmed') return;
            $effects[$effect] = ['status'=>$status,'finished_at'=>time()];
            if ($message) $effects[$effect]['message'] = $message;
            $values = ['effects'=>$effects];
            if ($print) {
                $result = $locked->result;
                $result['print'] = $print;
                $values['result'] = $result;
            } elseif ($effect === 'pdf' && ($effects['print']['status'] ?? '') === 'disabled') {
                $result = $locked->result;
                $result['print'] = ['auto_printed'=>false,'print_order_id'=>null,'reason'=>'not_requested'];
                $values['result'] = $result;
            }
            $locked->update($values);
        });
    }
}
