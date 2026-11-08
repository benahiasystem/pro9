<?php

namespace Modules\Report\Services;

use App\Support\Exact\Decimal as D;

// ######## INICIO CALCULOS EXACTOS PRO9 ########
/** Read frozen line operands and convert only into the chosen report currency. */
final class GeneralItemAmounts
{
    public static function forLine($line, $document, bool $convertToVes, array $cost): array
    {
        $currency = $document->currency_type_id;
        $target = $convertToVes && $currency === 'USD' ? 'VES' : $currency;
        $values = ['quantity'=>D::readRequired($line, 'quantity')];
        foreach (['unit_value','unit_price','total_value','total_discount','total_igv','total'] as $field) {
            $values[$field] = D::convert(D::readRequired($line, $field), $currency, $target, $document->exchange_rate_sale);
        }
        $values['total_item_purchase'] = $cost['amount'];
        $values['utility_item'] = $values['total']->minus($cost['amount']);
        return ['currency'=>$target, 'values'=>$values, 'cost_source'=>$cost['source']];
    }

    /** Catalogue prices are current estimates, never snapshots of the document. */
    public static function catalogPrice($item, string $field, $document, string $currency)
    {
        return D::convert(D::readRequired($item, $field), $item->currency_type_id, $currency, $document->exchange_rate_sale);
    }
}
// ######## FIN CALCULOS EXACTOS PRO9 ########
