<?php

// ######## INICIO MIGRACIÓN MONEDA VENEZUELA ########

namespace Modules\WhatsAppBot\Services\Tools;

use App\Models\Tenant\Item;
use App\Models\Tenant\Person;
use App\Services\SalesCustomerIdentityPolicy;
use Illuminate\Validation\ValidationException;
use Modules\WhatsAppBot\Services\Facturalo\DraftPricing;
use App\Support\Exact\Decimal as D;
use App\Models\Tenant\ExchangeRate;

class CreateDocumentTool implements ToolInterface
{
    private const DOC_TYPE_FACTURA = '01';

    public function name(): string
    {
        return 'create_document';
    }

    public function definition(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => $this->name(),
                // ########## INICIO CAMBIO SOLO FACTURAS Y NOTAS DE VENTA
                'description' => 'Prepara una factura para emisión. NO emite todavía. Devuelve un resumen para que el vendedor confirme.',
                // ######### FIN CAMBIO SOLO FACTURAS Y NOTAS DE VENTA
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'document_type' => [
                            'type' => 'string',
                            // ########## INICIO CAMBIO SOLO FACTURAS Y NOTAS DE VENTA
                            'enum' => ['factura'],
                            'description' => 'Tipo de comprobante a emitir: factura.',
                            // ######### FIN CAMBIO SOLO FACTURAS Y NOTAS DE VENTA
                        ],
                        'customer_id' => [
                            // ########## INICIO CAMBIO SOLO FACTURAS Y NOTAS DE VENTA
                            'type' => 'integer',
                            'description' => 'ID de un cliente con tipo de identidad activo para ventas (id de Person).',
                            // ######### FIN CAMBIO SOLO FACTURAS Y NOTAS DE VENTA
                        ],
                        'items' => [
                            'type' => 'array',
                            'description' => 'Líneas del comprobante.',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'item_id' => ['type' => 'integer', 'description' => 'ID del Item del catálogo.'],
                                    'quantity' => ['type' => 'number', 'description' => 'Cantidad a vender.'],
                                ],
                                'required' => ['item_id', 'quantity'],
                            ],
                        ],
                        'observations' => [
                            'type' => 'string',
                            'description' => 'Observaciones opcionales para el comprobante.',
                        ],
                        'discount_amount' => [
                            'type' => 'number',
                            'description' => 'Descuento global fijo en bolívares sobre la base imponible del IVA. Úsalo sólo si el vendedor lo solicitó; debe ser menor al subtotal sin IVA.',
                        ],
                    ],
                    'required' => ['document_type', 'customer_id', 'items'],
                ],
            ],
        ];
    }

    public function execute(array $arguments): array
    {
        $documentType = $arguments['document_type'] ?? null;
        $customerId = $arguments['customer_id'] ?? null;
        $items = $arguments['items'] ?? [];
        $observations = trim((string) ($arguments['observations'] ?? ''));
        $discountAmount = $arguments['discount_amount'] ?? '0';

        // ########## INICIO CAMBIO SOLO FACTURAS Y NOTAS DE VENTA
        if ($documentType !== 'factura') {
            return ['status' => 'error', 'error' => 'document_type debe ser "factura".'];
        }
        // ######### FIN CAMBIO SOLO FACTURAS Y NOTAS DE VENTA
        if (empty($items)) {
            return ['status' => 'error', 'error' => 'Debes incluir al menos un item.'];
        }

        // ########## INICIO CAMBIO SOLO FACTURAS Y NOTAS DE VENTA
        $documentTypeId = self::DOC_TYPE_FACTURA;
        // ######### FIN CAMBIO SOLO FACTURAS Y NOTAS DE VENTA

        // ########## INICIO CAMBIO SOLO FACTURAS Y NOTAS DE VENTA
        $customer = $customerId ? Person::find($customerId) : null;
        if (!$customer) {
            return ['status' => 'error', 'error' => "Cliente id={$customerId} no encontrado."];
        }
        // ######## INICIO POLITICA IDENTIDAD ACTIVA EN VENTAS ########
        try {
            $customer = SalesCustomerIdentityPolicy::assertCustomerAllowed($customerId);
        } catch (ValidationException $exception) {
            return ['status' => 'error', 'error' => 'El cliente no tiene un tipo de identidad activo para ventas.'];
        }
        // ######## FIN POLITICA IDENTIDAD ACTIVA EN VENTAS ########
        // ######### FIN CAMBIO SOLO FACTURAS Y NOTAS DE VENTA

        $lines = [];
        foreach ($items as $line) {
            $item = Item::find($line['item_id'] ?? null);
            if (!$item) {
                return ['status' => 'error', 'error' => "Item id={$line['item_id']} no existe."];
            }
            // Defensa anti-alucinacion: el LLM a veces pasa item_ids de items
            // placeholder (ej. "Costo de Envio" con precio 0). Rechazamos en vez
            // de armar un draft invalido. El LLM al ver el error reintenta con
            // un id correcto del search_items previo.
            $itemDisplayName = $item->description ?: $item->name;
            if ((float) $item->sale_unit_price <= 0 || empty(trim($itemDisplayName ?? ''))) {
                return [
                    'status' => 'error',
                    'error' => "Item id={$item->id} tiene precio en 0 o sin nombre — no es un producto vendible. Llama search_items primero y usa el item_id correcto del producto que pidio el vendedor.",
                ];
            }
            if ($item->item_type_id !== '01') {
                return [
                    'status' => 'error',
                    'error' => "Item id={$item->id} no es un producto vendible (tipo={$item->item_type_id}). Llama search_items primero.",
                ];
            }
            // La descripción es necesaria para identificar la partida en el PDF.
            if (empty(trim($item->name ?? ''))) {
                $shownName = $item->description ?: ('id=' . $item->id);
                return [
                    'status' => 'error',
                    'error' => "El producto \"{$shownName}\" no tiene descripción en su ficha. Complétala y vuelve a intentar.",
                ];
            }
            $lines[] = [
                'item_id' => (int) $item->id,
                'item_name' => $item->description ?: $item->name,
                'item_code' => $item->item_code ?: $item->internal_id,
                'quantity' => $line['quantity'] ?? '1',
                'price' => (string) $item->sale_unit_price,
                'price_currency' => $item->currency_type_id,
                'includes_iva' => (bool) $item->has_igv,
            ];
        }
        // ######## INICIO CALCULOS EXACTOS PRO9 ########
        $rate = ExchangeRate::where('date', '<=', now('America/Caracas')->format('Y-m-d'))->orderByDesc('date')->first();
        try {
            $pricing = DraftPricing::calculate($lines, $rate?->sale, $discountAmount);
        } catch (ValidationException $exception) {
            return ['status'=>'error', 'error'=>collect($exception->errors())->flatten()->first()];
        }
        $lines = array_map(fn ($line) => array_replace($line, [
            'unit_price_with_igv'=>$line['unit_price'], 'line_total'=>$line['total'], 'line_igv'=>$line['total_igv'],
        ]), $pricing['items']);
        // ######## FIN CALCULOS EXACTOS PRO9 ########

        $draft = [
            'document_type_id' => $documentTypeId,
            'document_type_label' => $documentType,
            'customer_id' => $customer ? (int) $customer->id : null,
            'customer_identity_document_type_id' => $customer ? $customer->identity_document_type_id : null,
            'customer_document_number' => $customer ? $customer->number : null,
            'customer_name' => $customer ? $customer->name : 'Consumidor final',
            'items' => $lines,
            'pricing_version' => 1,
            'operation_key' => (string) \Illuminate\Support\Str::uuid(),
            'exchange_rate_sale' => $pricing['exchange_rate_sale'],
            'subtotal' => $pricing['total_value'],
            'igv' => $pricing['total_igv'],
            'total' => $pricing['total_payable'],
            'discount_amount' => $pricing['discount_amount'],
            'discount_base' => $pricing['discount_base'],
            'observations' => $observations !== '' ? $observations : null,
        ];

        $summary = $this->buildSummaryText($draft);

        return [
            'status' => 'ready_for_confirmation',
            'summary_text' => $summary,
            'draft' => $draft,
            'message_to_seller' => 'Voy a emitir este comprobante. Por favor confirma con "sí" o cancela con "no".',
        ];
    }

    private function buildSummaryText(array $draft): string
    {
        $type = ucfirst($draft['document_type_label']);
        $lines = [];
        $lines[] = "=== {$type} a emitir ===";
        $lines[] = 'Cliente: ' . $draft['customer_name']
            . ($draft['customer_document_number'] ? ' (' . $draft['customer_document_number'] . ')' : '');
        $lines[] = 'Tasa: Bs. ' . $draft['exchange_rate_sale'] . ' por USD';
        $lines[] = '';
        $lines[] = 'Items:';
        foreach ($draft['items'] as $line) {
            $lines[] = '  - ' . $line['quantity'] . ' x ' . $line['item_name']
                . ' (Bs. ' . D::format($line['unit_price_with_igv'], 2) . ')'
                . ' = Bs. ' . D::format($line['line_total'], 2);
        }
        $lines[] = '';
        if (!empty($draft['discount_amount']) && $draft['discount_amount'] > 0) {
            $lines[] = 'Subtotal sin descuento: Bs. ' . D::format($draft['discount_base'], 2);
            $lines[] = 'Descuento: -Bs. ' . D::format($draft['discount_amount'], 2);
        }
        $lines[] = 'Subtotal: Bs. ' . D::format($draft['subtotal'], 2);
        // ########## INICIO CAMBIO IGV A IVA
        $lines[] = 'IVA: Bs. ' . D::format($draft['igv'], 2);
        // ######### FIN CAMBIO IGV A IVA
        $lines[] = 'Total: Bs. ' . D::format($draft['total'], 2);
        if ($draft['observations']) {
            $lines[] = '';
            $lines[] = 'Obs: ' . $draft['observations'];
        }

        return implode("\n", $lines);
    }
}

// ######## FIN MIGRACIÓN MONEDA VENEZUELA ########
