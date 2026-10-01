<?php

namespace Modules\WhatsAppBot\Services\Tools;

use App\Models\Tenant\Document;

class QueryDocumentStatusTool implements ToolInterface
{
    public function name(): string
    {
        return 'query_document_status';
    }

    public function definition(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => $this->name(),
                'description' => 'Consulta el estado local de una Factura o nota de crédito/débito. El registro local no implica transmisión ni aceptación por una autoridad fiscal.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'number_full' => [
                            'type' => 'string',
                            'description' => 'Serie y número del comprobante (FF01-15, FC01-9), o sólo número si no tiene serie.',
                        ],
                        'document_id' => [
                            'type' => 'integer',
                            'description' => 'Alternativa: ID interno del Document (si lo conoces).',
                        ],
                        'establishment_id' => [
                            'type' => 'integer',
                            'description' => 'Sucursal requerida cuando el comprobante no tiene serie.',
                        ],
                        'document_type_id' => [
                            'type' => 'string',
                            'enum' => ['01', '07', '08'],
                            'description' => 'Tipo: 01 factura (por defecto), 07 crédito u 08 débito.',
                        ],
                    ],
                ],
            ],
        ];
    }

    public function execute(array $arguments): array
    {
        $document = null;

        if (!empty($arguments['document_id'])) {
            $document = Document::find((int) $arguments['document_id']);
        }

        if (!$document && !empty($arguments['number_full'])) {
            $parts = \App\Services\Fiscal\FiscalIdentity::parseNumberFull((string) $arguments['number_full']);
            if ($parts !== null) {
                [$series, $number] = $parts;
                $branch = $arguments['establishment_id'] ?? optional(auth()->user())->establishment_id;
                if ($series === '' && !$branch) return ['status' => 'error', 'error' => 'Indique la sucursal del comprobante sin serie.'];
                $query = Document::where('series', $series)->where('number', (int) ltrim($number, '0'));
                if ($series === '') $query->where('establishment_id', $branch)->where('document_type_id', $arguments['document_type_id'] ?? '01');
                $document = $query->first();
            }
        }

        if (!$document) {
            return ['status' => 'error', 'error' => 'No se encontró el comprobante.'];
        }

        return [
            'number_full' => $document->number_full,
            'type' => $document->document_type->description,
            'date' => $document->date_of_issue?->format('Y-m-d'),
            'total' => (float) $document->total,
            'local_state' => $this->stateDescription($document->state_type_id),
            'state_code' => $document->state_type_id,
            'has_pdf' => (bool) $document->has_pdf,
        ];
    }

    private function stateDescription(?string $stateId): string
    {
        return match ($stateId) {
            '01' => 'registrado localmente',
            '11' => 'anulado',
            '13' => 'por anular',
            default => 'estado desconocido',
        };
    }
}
