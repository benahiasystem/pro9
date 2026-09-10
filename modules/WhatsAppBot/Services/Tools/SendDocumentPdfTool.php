<?php

namespace Modules\WhatsAppBot\Services\Tools;

use App\Models\Tenant\Document;
use Modules\WhatsAppBot\Services\Evolution\EvolutionSender;

class SendDocumentPdfTool implements ToolInterface
{
    /** @var EvolutionSender */
    private $sender;

    /** @var string */
    private $toPhone;

    /** @var int|null */
    private $sessionId;

    public function __construct(
        EvolutionSender $sender,
        string $toPhone,
        ?int $sessionId = null
    ) {
        $this->sender = $sender;
        $this->toPhone = $toPhone;
        $this->sessionId = $sessionId;
    }

    public function name(): string
    {
        return 'send_document_pdf';
    }

    public function definition(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => $this->name(),
                'description' => 'Envía el PDF de una Factura o nota de crédito/débito al WhatsApp del vendedor. Recibe el id del Document (no la serie-número).',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'document_id' => [
                            'type' => 'integer',
                            'description' => 'ID interno del Document a reenviar (obtenido vía list_recent_documents).',
                        ],
                    ],
                    'required' => ['document_id'],
                ],
            ],
        ];
    }

    public function execute(array $arguments): array
    {
        $documentId = (int) ($arguments['document_id'] ?? 0);
        if ($documentId <= 0) {
            return ['status' => 'error', 'error' => 'Falta document_id.'];
        }

        $document = Document::find($documentId);
        if (!$document) {
            return ['status' => 'error', 'error' => "No existe el comprobante id={$documentId}."];
        }

        if (empty($document->filename)) {
            return ['status' => 'error', 'error' => 'El comprobante no tiene filename. No se puede localizar su PDF.'];
        }

        $numberFull = $document->series . '-' . $document->number;
        $displayFilename = $numberFull . '.pdf';
        $caption = $document->document_type->description . ' ' . $numberFull;

        $result = $this->sender->sendDocumentPdf(
            $this->toPhone,
            $document->filename,
            $displayFilename,
            $caption,
            $this->sessionId
        );

        if (!$result) {
            return ['status' => 'error', 'error' => 'No se pudo enviar el PDF.'];
        }

        return [
            'status' => 'sent',
            'number_full' => $numberFull,
            'document_id' => $document->id,
        ];
    }
}
