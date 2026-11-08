<?php

namespace App\Support\Exact;

use App\Models\Tenant\Kardex;

/** Presentation operands for the legacy stock ledger; quantities stay rational. */
final class LegacyKardexRow
{
    public static function from(Kardex $row): array
    {
        $quantity=$row->exactValue('quantity');
        $dispatch=(array) ($row->calculation_snapshot['inputs']['dispatch_global_inventory'] ?? []);
        $entry=$row->type === 'sale' ? Decimal::of(0) : $quantity;
        $exit=$row->type === 'sale' ? $quantity : Decimal::of(0);
        if ($dispatch) {
            $description=!empty($dispatch['reversal']) ? 'Anulación de orden de entrega' : 'Orden de entrega';
            $number=$dispatch['number_full'] ?? '-';
        } elseif ($row->type === 'sale') {
            $description=$row->sale_note_id ? 'Nota de venta' : (optional($row->document)->document_type_id === '07'
                ? 'Nota de crédito' : ($quantity->isNegative() ? 'Anulación' : 'Venta'));
            $number=optional($row->document ?: $row->sale_note)->number_full ?? '-';
        } elseif ($row->type === 'purchase') {
            $description='Compra';
            $purchase=$row->purchase ?: $row->purchase_settlement;
            $number=$purchase ? \App\Services\Fiscal\FiscalIdentity::numberFull($purchase->series, $purchase->number) : '-';
        } else {
            $description='Stock inicial'; $number='-';
        }
        return ['description'=>$description,'number'=>$number,'entry'=>$entry,'exit'=>$exit,'delta'=>$entry->minus($exit)];
    }
}
