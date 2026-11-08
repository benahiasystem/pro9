<?php

namespace App\Services\Fiscal;

use App\Models\Tenant\PurchaseItem;
use App\Support\Exact\Decimal as D;

/** Recognize only a complete, unchanged server line; never copy a client snapshot. */
final class PurchaseUnchangedLine
{
    private static function canonical($value)
    {
        if (is_object($value)) $value=(array)$value;
        if (!is_array($value)) return $value;
        if (!array_is_list($value)) ksort($value);
        foreach ($value as $key=>$entry) $value[$key]=self::canonical($entry);
        return $value;
    }

    public static function matches(PurchaseItem $line, array $row): bool
    {
        foreach (['item_id','warehouse_id','item_lot_group_id','lot_code','date_of_due','affectation_igv_type_id','price_type_id','name_product_pdf'] as $field) {
            if ((string)($row[$field] ?? '') !== (string)($line->$field ?? '')) return false;
        }
        if (self::canonical($row['item'] ?? null)!==self::canonical($line->item)) return false;
        foreach (['attributes','discounts','charges'] as $field) {
            if (self::canonical($row[$field] ?? null)!==self::canonical($line->$field)) return false;
        }
        foreach (['quantity','unit_value','total_base_igv','percentage_igv','total_igv','total_base_other_taxes','percentage_other_taxes',
            'total_other_taxes','total_taxes','unit_price','total_value','total_charge','total_discount','total'] as $field) {
            if (!array_key_exists($field,$row)) return false;
            try { if (D::represent(D::of(D::input($row[$field], 'items.'.$field)))!==D::represent($line->exactValue($field))) return false; }
            catch (\Throwable $error) { return false; }
        }
        foreach (['quantity_input','price_input'] as $field) {
            if (array_key_exists($field,$row) && self::canonical($row[$field])!==self::canonical($line->calculation_snapshot['inputs'][$field] ?? null)) return false;
        }
        $stored=$line->lots()->orderBy('id')->lockForUpdate()->get();
        if ($stored->isNotEmpty() && !isset($row['lots'])) return false;
        $posted=collect($row['lots'] ?? [])->keyBy('id');
        if ($posted->count()!==$stored->count() || count($row['lots'] ?? [])!==$stored->count()) return false;
        foreach ($stored as $serial) {
            $candidate=$posted->get($serial->id);
            if (!$candidate || (string)($candidate['series'] ?? '') !== (string)$serial->series) return false;
            foreach (['date','state','warehouse_id'] as $field) {
                if (array_key_exists($field,$candidate) && (string)($candidate[$field] ?? '') !== (string)($serial->$field ?? '')) return false;
            }
        }
        return true;
    }
}
