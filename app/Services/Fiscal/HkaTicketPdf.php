<?php

namespace App\Services\Fiscal;

use App\CoreFacturalo\Facturalo;
use App\CoreFacturalo\Helpers\QrCode\QrCodeGenerate;
use App\Models\Tenant\Document;

/** The existing local ticket, with an HKA query QR only for explicit downloads. */
class HkaTicketPdf
{
    public function render(Document $document, string $consultaUrl): string
    {
        // The QR library includes its four-module white border by default.
        $qr = (new QrCodeGenerate())->displayPNGBase64($consultaUrl, 300, 'M');
        return app(Facturalo::class)->createPdf($document, 'invoice', 'ticket', 'string', ['hka_ticket_qr' => $qr]);
    }
}
