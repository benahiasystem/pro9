<?php

namespace Modules\WhatsAppBot\Services\Tools;

use App\Models\Tenant\Document;

class ListRecentDocumentsTool implements ToolInterface
{
    private const MAX_RESULTS = 10;

    public function name(): string
    {
        return 'list_recent_documents';
    }

    public function definition(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => $this->name(),
                'description' => 'Lista las facturas registradas recientemente. Devuelve número, fecha, cliente, total y estado local del documento.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'limit' => [
                            'type' => 'integer',
                            'description' => 'Cantidad de comprobantes a listar (máximo 10). Si no se indica, devuelve los últimos 5.',
                        ],
                        'document_type' => [
                            'type' => 'string',
                            'enum' => ['factura'],
                            'description' => 'Tipo de documento: factura.',
                        ],
                        'customer_document' => [
                            'type' => 'string',
                            'description' => 'Filtrar por DNI o RIF del cliente (opcional).',
                        ],
                    ],
                ],
            ],
        ];
    }

    public function execute(array $arguments): array
    {
        $limit = (int) ($arguments['limit'] ?? 5);
        $limit = max(1, min($limit, self::MAX_RESULTS));
        $customerDoc = trim((string) ($arguments['customer_document'] ?? ''));
        $docType = $arguments['document_type'] ?? null;

        if ($docType !== null && $docType !== 'factura') {
            return ['error' => 'El tipo de documento solicitado no está disponible.'];
        }

        $query = Document::query()
            ->where('document_type_id', '01')
            ->orderByDesc('id')
            ->limit($limit);

        if ($customerDoc !== '') {
            $query->whereHas('person', function ($q) use ($customerDoc) {
                $q->where('number', $customerDoc);
            });
        }

        $documents = $query->get([
            'id', 'document_type_id', 'series', 'number',
            'date_of_issue', 'customer_id', 'total', 'state_type_id',
        ]);

        return [
            'count' => $documents->count(),
            'documents' => $documents->map(fn ($d) => [
                'id' => $d->id,
                'number_full' => $d->series . '-' . $d->number,
                'type' => 'factura',
                'date' => optional($d->date_of_issue)->format('Y-m-d'),
                'customer_name' => optional($d->person)->name,
                'customer_document' => optional($d->person)->number,
                'total' => (float) $d->total,
                'state' => $this->stateDescription($d->state_type_id),
            ])->all(),
        ];
    }

    private function stateDescription(?string $stateId): string
    {
        $states = [
            '01' => 'registrado',
            '05' => 'aceptado',
            '07' => 'rechazado',
            '11' => 'anulado',
            '13' => 'por anular',
        ];

        return $states[$stateId] ?? 'desconocido';
    }
}
