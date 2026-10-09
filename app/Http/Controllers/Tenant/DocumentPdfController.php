<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Document;
use App\Services\Fiscal\HkaPdf;
use Symfony\Component\HttpFoundation\HeaderUtils;

class DocumentPdfController extends Controller
{
    public function download(string $external_id, string $format)
    {
        abort_unless(in_array($format, ['a4', 'a5', 'ticket'], true), 404);
        $document = Document::where('external_id', $external_id)->firstOrFail();
        DocumentFiscalController::authorizeDocument($document);
        if (!HkaPdf::applies($document)) {
            return app(DownloadController::class)->toPrint('document', $external_id, $format);
        }
        $pdf = app(HkaPdf::class)->download($document, $format);
        $filename = \App\Services\Fiscal\DocumentFileName::visible(app(\App\Services\Fiscal\HkaPdfStore::class)->filename($document))
            .($format === 'ticket' ? '-80mm' : '').'.pdf';
        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => HeaderUtils::makeDisposition('attachment', $filename),
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
