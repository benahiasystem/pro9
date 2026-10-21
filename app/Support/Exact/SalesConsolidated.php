<?php

namespace App\Support\Exact;

// ######## INICIO CALCULOS EXACTOS PRO9 ########
/** Shared, unformatted report values. Never consult mutable catalog prices. */
final class SalesConsolidated
{
    public const COMPONENT_LABELS = ['line_total'=>'Partidas', 'header_discount'=>'Descuentos de cabecera',
        'header_charge'=>'Cargos de cabecera', 'header_iva_adjustment'=>'Ajuste de IVA',
        'header_other_taxes'=>'Otros impuestos de cabecera', 'header_prepayment'=>'Anticipos'];

    /** Final presentation only; signed components add up to the represented document total. */
    public static function displayComponents(array $summary): array
    {
        $rows = [];
        foreach (self::COMPONENT_LABELS as $field=>$label) {
            $value = Decimal::readRequired($summary, $field);
            if (in_array($field, ['header_discount', 'header_prepayment'], true)) $value = $value->negated();
            $rows[] = ['id'=>$field, 'amount'=>Decimal::represent($value), 'calculation_snapshot'=>Decimal::snapshot(['amount'=>$value])];
        }
        $display = [];
        foreach (ReportProjection::money(new ReportDataset($rows), ['amount']) as $row) $display[$row['id']] = $row['display']['amount'];
        return $display;
    }

    public static function line($record, ?array $components = null): array
    {
        $data = is_array($record) ? $record : $record->getAttributes();
        $currency = $data['currency_type_id'] ?? null;
        if (!in_array($currency, ['VES', 'USD'], true)) throw new \UnexpectedValueException('Moneda ausente o inválida en el consolidado.');
        $quantity = Decimal::readRequired($record, 'quantity');
        $components ??= array_replace(array_fill_keys(HeaderAllocation::FIELDS, Decimal::of(0)),
            ['line_total'=>Decimal::readRequired($record, 'total'), 'report_total'=>Decimal::readRequired($record, 'total')]);
        $original = $components['report_total'];
        if (($data['document_type_id'] ?? '') === '07') {
            $quantity = $quantity->negated();
            $original = $original->negated();
            $components = array_map(static fn($value)=>$value->negated(), $components);
        }
        if ($currency === 'USD') {
            $rate = $data['exchange_rate_sale'] ?? null;
            if ($rate === null || !Decimal::of($rate)->isPositive()) throw new \UnexpectedValueException('Falta la tasa histórica de la operación USD.');
            $total = Decimal::convert($original, 'USD', 'VES', $rate);
        } else $total = $original;
        $item = is_array($record) ? ($record['item'] ?? []) : $record->item;
        if (is_string($item)) $item = JsonDecimals::decode($item);
        $item = (array)$item;
        $presentation = (array)($item['presentation'] ?? []);
        $relation = !is_array($record) && $record->relationLoaded('relation_item') ? $record->relation_item : null;
        $brand = $relation && $relation->relationLoaded('brand') ? $relation->brand : null;
        $category = $relation && $relation->relationLoaded('category') ? $relation->category : null;
        $values = ['quantity'=>$quantity, 'item_quantity'=>$quantity, 'total_sale'=>$total, 'total_original'=>$original,
            'total_ves'=>$currency === 'VES' ? $original : Decimal::of(0),
            'total_usd'=>$currency === 'USD' ? $original : Decimal::of(0)];
        foreach ($components as $field=>$value) $values[$field] = Decimal::convert($value, $currency, 'VES', $data['exchange_rate_sale'] ?? null);
        return array_merge([
            'id'=>$data['id'],
            'report_key'=>($data['source_type'] ?? 'document').':'.$data['id'],
            'item_id'=>$data['item_id'],
            'item_internal_id'=>$item['internal_id'] ?? ($relation->internal_id ?? ''),
            'item_unit_type_id'=>$presentation['unit_type_id'] ?? $item['unit_type_id'] ?? ($relation->unit_type_id ?? ''),
            'item_description'=>$item['description'] ?? '',
            'brand'=>$brand ? ['id'=>$brand->id, 'name'=>$brand->name] : ['id'=>'', 'name'=>''],
            'category'=>$category->name ?? '',
            'category_name'=>$category->name ?? '',
            'series'=>$data['series'] ?? '',
            'number'=>$data['number'] ?? '',
            'date_of_issue'=>$data['date_of_issue'] ?? '',
            'currency_type_id'=>$currency,
            'report_currency_type_id'=>'VES',
            'exchange_rate_sale'=>$data['exchange_rate_sale'] ?? null,
            'currency_totals'=>[$currency=>Decimal::represent($original)],
            'calculation_snapshot'=>Decimal::snapshot($values),
        ], Decimal::values($values));
    }

    public static function summary(iterable $rows): array
    {
        $quantity = $total = Decimal::of(0);
        $components = array_fill_keys(HeaderAllocation::FIELDS, Decimal::of(0));
        $currencies = ['VES'=>Decimal::of(0), 'USD'=>Decimal::of(0)];
        foreach ($rows as $row) {
            $quantity = $quantity->plus(Decimal::readRequired($row, 'quantity'))->simplified();
            $total = $total->plus(Decimal::readRequired($row, 'total_sale'))->simplified();
            foreach ($components as $field=>$value) $components[$field] = $value->plus(Decimal::readRequired($row, $field))->simplified();
            foreach ($currencies as $currency=>$value) {
                if (isset($row['currency_type_id'])) {
                    if ($row['currency_type_id'] === $currency) $currencies[$currency] = $value->plus(Decimal::readRequired($row, 'total_original'))->simplified();
                } else {
                    $currencies[$currency] = $value->plus(Decimal::readRequired($row, 'total_'.strtolower($currency)))->simplified();
                }
            }
        }
        $values = ['quantity'=>$quantity, 'total_sale'=>$total, 'total_ves'=>$currencies['VES'], 'total_usd'=>$currencies['USD']] + $components;
        return array_merge(Decimal::values($values), ['currency_totals'=>Decimal::values($currencies),
            'calculation_snapshot'=>Decimal::snapshot($values), 'currency_type_id'=>'VES']);
    }

    public static function group(iterable $rows): array
    {
        $groups = [];
        foreach ($rows as $row) {
            $key = json_encode([$row['item_id'], $row['item_unit_type_id']], JSON_THROW_ON_ERROR);
            if (!isset($groups[$key])) $groups[$key] = ['first'=>$row, 'quantity'=>Decimal::of(0), 'total_sale'=>Decimal::of(0), 'VES'=>Decimal::of(0), 'USD'=>Decimal::of(0), 'currencies'=>[]] + array_fill_keys(HeaderAllocation::FIELDS, Decimal::of(0));
            $group = &$groups[$key];
            foreach (['quantity', 'total_sale'] as $field) $group[$field] = $group[$field]->plus(Decimal::readRequired($row, $field))->simplified();
            foreach (HeaderAllocation::FIELDS as $field) $group[$field] = $group[$field]->plus(Decimal::readRequired($row, $field))->simplified();
            $currency = $row['currency_type_id'];
            $group['currencies'][$currency] = true;
            $group[$currency] = $group[$currency]->plus(Decimal::readRequired($row, 'total_original'))->simplified();
            unset($group);
        }
        return array_values(array_map(static function (array $group): array {
            $values = ['quantity'=>$group['quantity'], 'total_sale'=>$group['total_sale'], 'total_ves'=>$group['VES'], 'total_usd'=>$group['USD']];
            foreach (HeaderAllocation::FIELDS as $field) $values[$field] = $group[$field];
            $first = $group['first'];
            unset($first['currency_type_id'], $first['total_original'], $first['item_quantity']);
            return array_merge($first, Decimal::values($values), ['id'=>json_encode([$first['item_id'], $first['item_unit_type_id']], JSON_THROW_ON_ERROR),
                'report_key'=>'item:'.json_encode([$first['item_id'], $first['item_unit_type_id']], JSON_THROW_ON_ERROR),
                'currency_totals'=>array_intersect_key(Decimal::values(['VES'=>$group['VES'], 'USD'=>$group['USD']]), $group['currencies']), 'calculation_snapshot'=>Decimal::snapshot($values)]);
        }, $groups));
    }

    /** Group one sorted key at a time; repeated lines do not grow memory usage. */
    public static function groupedDataset(iterable $rows): ReportDataset
    {
        $key = static fn($row)=>json_encode([$row['item_id'], $row['item_unit_type_id']], JSON_THROW_ON_ERROR);
        // Compact each batch before external sorting; repeated products need very few runs.
        $partials = new ReportDataset((function () use ($rows) {
            $batch = [];
            foreach ($rows as $row) {
                $batch[] = $row;
                if (count($batch) === 500) {
                    foreach (self::group($batch) as $group) yield $group;
                    $batch = [];
                }
            }
            if ($batch) foreach (self::group($batch) as $group) yield $group;
        })());
        $sorted = $partials->sorted(static fn($a,$b)=>strcmp($key($a), $key($b)));
        return new ReportDataset((function () use ($sorted, $key) {
            $currentKey = null; $first = null; $values = []; $currencies = [];
            $finish = static function ($first, $values, $currencies): array {
                return array_merge($first, Decimal::values($values), ['calculation_snapshot'=>Decimal::snapshot($values),
                    'currency_totals'=>array_intersect_key(Decimal::values(['VES'=>$values['total_ves'], 'USD'=>$values['total_usd']]), $currencies)]);
            };
            foreach ($sorted as $row) {
                $rowKey = $key($row);
                if ($currentKey !== $rowKey) {
                    if ($first !== null) yield $finish($first, $values, $currencies);
                    $first = $row; $currentKey = $rowKey;
                    $currencies = [];
                    $values = array_fill_keys(array_merge(['quantity','total_sale','total_ves','total_usd'], HeaderAllocation::FIELDS), Decimal::of(0));
                }
                foreach (['quantity','total_sale'] as $field) $values[$field] = $values[$field]->plus(Decimal::readRequired($row,$field))->simplified();
                foreach (['total_ves','total_usd'] as $field) $values[$field] = $values[$field]->plus(Decimal::readRequired($row,$field))->simplified();
                foreach (HeaderAllocation::FIELDS as $field) $values[$field] = $values[$field]->plus(Decimal::readRequired($row,$field))->simplified();
                foreach (array_keys($row['currency_totals']) as $currency) $currencies[$currency] = true;
            }
            if ($first !== null) yield $finish($first, $values, $currencies);
        })());
    }
}
// ######## FIN CALCULOS EXACTOS PRO9 ########
