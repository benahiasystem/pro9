<?php

namespace App\Services\Fiscal;

use App\CoreFacturalo\Facturalo;
use App\CoreFacturalo\Helpers\QrCode\QrCodeGenerate;
use App\Models\Tenant\Document;

/** The existing local ticket, with an HKA query QR only for explicit downloads. */
class HkaTicketPdf
{
    public function render(Document $document, ?string $consultaUrl = null): string
    {
        // The QR library includes its four-module white border by default.
        $options = $consultaUrl === null ? [] : ['hka_ticket_qr' => (new QrCodeGenerate())->displayPNGBase64($consultaUrl, 300, 'M')];
        return app(Facturalo::class)->createPdf($document, ['01'=>'invoice','07'=>'credit','08'=>'debit'][$document->document_type_id], 'ticket', 'string', $options);
    }
}
