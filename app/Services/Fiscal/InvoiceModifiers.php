<?php

namespace App\Services\Fiscal;

use App\Database\IntegerTransport;
use App\Models\Tenant\{Document, Item};
use App\Support\Exact\Decimal as D;
use Brick\Math\BigRational;

final class InvoiceModifiers
{
    /** The API transform keeps the original invoice rows in their submitted order. */
    public static function byLine(Document $invoice, array $lines): array
    {
        if ($invoice->sale_note_id || $invoice->order_note_id || $invoice->dispatch_id
            || $invoice->sale_notes_relateds || $invoice->dispatches_relateds) {
            return [];
        }

        $components = [];
        $keys = array_keys($lines);
        foreach (array_values((array) data_get($invoice->data_json, 'items', [])) as $position => $source) {
            $root = $lines[$keys[$position] ?? '']['item_id'] ?? null;
            $lineComponents = self::fromItem($source, $root);
            if ($lineComponents) $components[$position] = $lineComponents;
        }

        if (!$components) return [];
        if (count((array) data_get($invoice->data_json, 'items', [])) !== count($keys)) {
            FiscalAmounts::error('items', 'Los productos adicionales deben corresponder a las partidas de la factura.');
        }
        $ids = array_unique(array_column(array_merge(...array_values($components)), 'item_id'));
        if (Item::whereIn('id', $ids)->count() !== count($ids)) {
            FiscalAmounts::error('items', 'Un producto adicional de la factura no está disponible.');
        }

        $byLine = [];
        foreach ($components as $position => $component) {
            $byLine[$keys[$position]] = $component;
        }
        return $byLine;
    }

    /** Read component selections from a submitted invoice or a persisted sale-note line. */
    public static function fromItem($source, $root): array
    {
        $components = [];
        $grouped = [];
        foreach ((array) data_get($source, 'modifiersApplied', []) as $group) {
            foreach ((array) data_get($group, 'items', []) as $selection) {
                if (data_get($selection, 'type') !== 'item' || !data_get($selection, 'item_id')) continue;
                $id = IntegerTransport::positiveKey(data_get($selection, 'item_id'));
                if ($id === null) FiscalAmounts::error('items', 'El modificador debe identificar un producto válido.');
                $grouped[$id] = ($grouped[$id] ?? 0) + 1;
            }
        }
        ksort($grouped);
        foreach ($grouped as $id => $quantity) {
            $components[] = ['item_id' => $id, 'quantity' => $quantity, 'invoice_modifier' => true];
        }
        if (!filter_var(data_get($source, 'esFusionado'), FILTER_VALIDATE_BOOLEAN)) return $components;

        $merged = [];
        foreach ((array) data_get($source, 'productosFusionados', []) as $selection) {
            $id = IntegerTransport::positiveKey(data_get($selection, 'id'));
            if ($id === null) FiscalAmounts::error('items', 'El producto fusionado debe tener un identificador válido.');
            if ((string) $id === (string) $root) continue;
            $amount = D::of(D::input(data_get($selection, 'quantity', 1), 'items.productosFusionados.quantity'));
            if (!$amount->isPositive()) FiscalAmounts::error('items', 'La cantidad del producto fusionado debe ser positiva.');
            $merged[$id] = D::of($merged[$id] ?? 0)->plus($amount);
        }
        ksort($merged);
        foreach ($merged as $id => $amount) {
            $components[] = ['item_id' => $id, 'merged_total' => $amount, 'invoice_modifier' => true];
        }
        return $components;
    }

    public static function saleNoteFactors($item, $root, $storedQuantity, BigRational $exactQuantity): array
    {
        $totals = [];
        foreach (self::fromItem($item, $root) as $component) {
            if (!$exactQuantity->isPositive()) {
                FiscalAmounts::error('items', 'La partida de origen con componentes debe tener una cantidad positiva.');
            }
            $id = $component['item_id'];
            // The sale-note movement used its stored decimal for each modifier.
            $amount = $component['merged_total'] ?? D::of($storedQuantity)->multipliedBy($component['quantity']);
            $totals[$id] = D::of($totals[$id] ?? 0)->plus($amount);
        }
        ksort($totals);
        foreach ($totals as &$amount) $amount = D::fraction($amount->dividedBy($exactQuantity));
        unset($amount);
        return $totals;
    }

    public static function components($item): array
    {
        return array_values(array_filter(array_map(
            static fn ($component) => (array) $component,
            (array) data_get($item, 'inventory_components', [])
        ), static fn (array $component) => ($component['invoice_modifier'] ?? false) === true));
    }
}
