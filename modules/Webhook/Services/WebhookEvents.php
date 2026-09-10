<?php

namespace Modules\Webhook\Services;

use Modules\Webhook\Services\Payloads\DocumentPayload;
use Modules\Webhook\Services\Payloads\PayloadBuilderInterface;
use Modules\Webhook\Services\Payloads\PurchaseCreatedPayload;

/**
 * Registro central de eventos de webhook.
 *
 * Para añadir un nuevo evento ver modules/Webhook/README.md
 * (constante + entrada en $map y, si depende de un estado local,
 * una línea más en $documentStateMap).
 */
class WebhookEvents
{
    const DOCUMENT_CREATED = 'document.created';
    const DOCUMENT_VOIDED = 'document.voided';
    const PURCHASE_CREATED = 'purchase.created';

    private static array $map = [
        self::DOCUMENT_CREATED => [
            'label' => 'Documento de venta emitido',
            'payload' => DocumentPayload::class,
        ],
        self::DOCUMENT_VOIDED => [
            'label' => 'Documento anulado (baja aceptada)',
            'payload' => DocumentPayload::class,
        ],
        self::PURCHASE_CREATED => [
            'label' => 'Compra registrada',
            'payload' => PurchaseCreatedPayload::class,
        ],
    ];

    /**
     * Mapa de estados locales que generan eventos adicionales.
     */
    private static array $documentStateMap = [
        '11' => self::DOCUMENT_VOIDED,
    ];

    /**
     * Lista para los checkboxes de la UI.
     *
     * @return array [['value' => ..., 'label' => ...], ...]
     */
    public static function all(): array
    {
        return collect(self::$map)
            ->map(fn ($item, $event) => ['value' => $event, 'label' => $item['label']])
            ->values()
            ->all();
    }

    /**
     * @return string[] nombres de eventos válidos (para validación)
     */
    public static function keys(): array
    {
        return array_keys(self::$map);
    }

    public static function label(string $event): ?string
    {
        return self::$map[$event]['label'] ?? null;
    }

    public static function payloadBuilder(string $event): PayloadBuilderInterface
    {
        return app(self::$map[$event]['payload']);
    }

    public static function forDocumentState(?string $stateTypeId): ?string
    {
        return self::$documentStateMap[$stateTypeId] ?? null;
    }
}
