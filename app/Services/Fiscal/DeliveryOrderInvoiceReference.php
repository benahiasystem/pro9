<?php

namespace App\Services\Fiscal;

use App\Models\Tenant\Document;
use App\Models\Tenant\DocumentItem;
use App\Support\Exact\Decimal as D;
use Illuminate\Support\Collection;

final class DeliveryOrderInvoiceReference
{
    public static function assertDocument(Document $invoice, $establishmentId, $customerId): void
    {
        DeliveryOrderInput::authorize((int) $invoice->establishment_id);
        if ($invoice->document_type_id !== '01'
            || in_array($invoice->state_type_id, ['09', '11'], true)
            || (string) $invoice->establishment_id !== (string) $establishmentId
            || ($customerId !== null && (string) $invoice->customer_id !== (string) $customerId)) {
            FiscalAmounts::error('reference_document_id', 'La factura relacionada debe estar vigente y pertenecer a la sucursal y al cliente.');
        }
    }

    public static function allocations(DocumentItem $line): array
    {
        $factor = FiscalNoteInventory::presentationQuantity($line->item);
        $quantity = $line->exactValue('quantity');
        $serials = array_values(array_filter(
            array_map(fn ($serial) => (array) $serial, (array) ($line->item->lots ?? [])),
            fn ($serial) => !empty($serial['has_sale'])
        ));
        $lots = $line->item->IdLoteSelected ?? null;

        if (!is_array($lots) || !$lots) {
            return [['lot' => is_array($lots) ? null : $lots, 'quantity' => $quantity->multipliedBy($factor), 'serials' => $serials]];
        }

        $allocations = [];
        foreach ($lots as $lot) {
            $lot = (array) $lot;
            $id = $lot['id'];
            $allocated = D::of($lot['compromise_quantity_exact'] ?? $lot['compromise_quantity'])->multipliedBy($factor);
            $assigned = count($lots) === 1
                ? $serials
                : array_values(array_filter($serials, fn ($serial) => (string) ($serial['lot_group_id'] ?? '') === (string) $id));
            $allocations[] = ['lot' => $id, 'quantity' => $allocated, 'serials' => $assigned];
        }
        if (array_sum(array_map(fn ($allocation) => count($allocation['serials']), $allocations)) !== count($serials)) {
            FiscalAmounts::error('items', 'La factura original no conserva la asignación de sus seriales a cada lote.');
        }
        return $allocations;
    }

    public static function assertItems(Collection $lines, array $rows): void
    {
        foreach (collect($rows)->groupBy('item_id') as $itemId => $requested) {
            $source = $lines->filter(fn ($line) => (string) $line->item_id === (string) $itemId);
            $physical = D::sum($source->map(fn ($line) => $line->exactValue('quantity')
                ->multipliedBy(FiscalNoteInventory::presentationQuantity($line->item))));
            if (D::sum($requested->pluck('quantity'))->isGreaterThan($physical)) {
                FiscalAmounts::error('items', 'Las cantidades y productos deben corresponder al documento comercial de origen.');
            }

            $lots = [];
            $serials = [];
            foreach ($source as $line) {
                foreach (self::allocations($line) as $allocation) {
                    $lot = (string) $allocation['lot'];
                    $lots[$lot] = D::of($lots[$lot] ?? 0)->plus($allocation['quantity']);
                    foreach ($allocation['serials'] as $serial) {
                        $serials[(string) $serial['id']] = $lot;
                    }
                }
            }
            foreach ($requested as $row) {
                $lot = (string) ($row['item']['IdLoteSelected'] ?? '');
                $remaining = D::of($lots[$lot] ?? 0)->minus($row['quantity']);
                if ($remaining->isNegative()) {
                    FiscalAmounts::error('items', 'El lote o la cantidad no corresponde a la factura de origen.');
                }
                $lots[$lot] = $remaining;
                foreach ($row['item']['lots'] ?? [] as $serial) {
                    $key = (string) $serial['id'];
                    if (($serials[$key] ?? null) !== $lot) {
                        FiscalAmounts::error('items', 'El serial no corresponde a la factura de origen y al lote seleccionado.');
                    }
                    unset($serials[$key]);
                }
            }
        }
    }
}
