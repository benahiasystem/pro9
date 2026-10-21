<?php

namespace App\Support\Exact;

use App\Models\Tenant\SaleNote;
use Illuminate\Validation\ValidationException;

// ######## INICIO CALCULOS EXACTOS PRO9 ########
/** Read-only preparation. Persisting a conversion must re-authorize its sources. */
final class SaleNoteConversionLines
{
    private const ADDITIVE=['quantity','total_value','total_base_igv','total_igv','total_taxes','total_discount',
        'total_charge','total_other_taxes','total','header_discount','header_charge','header_iva_adjustment',
        'header_other_taxes','header_prepayment','report_total'];

    public static function preview(array $ids, bool $selectAll=false): array
    {
        $rows=[];$context=null;
        $groups=self::group(self::sourceRows($ids,$rows,$context,$selectAll));
        return ['data'=>$rows,'grouped_items'=>$groups,'conversion_context'=>[
            'customer_id'=>$context[0] ?? null,'establishment_id'=>$context[1] ?? null,
            'currency_type_id'=>$context[2] ?? null,'exchange_rate_sale'=>$context[3] ?? null,
        ]];
    }

    private static function sourceRows(array $ids,array &$rows,?array &$context,bool $selectAll): \Generator
    {
        $db=\Illuminate\Support\Facades\DB::connection('tenant');
        $affectations=$db->table((new \App\Models\Tenant\Catalogs\AffectationIgvType())->getTable())->get()->keyBy('id');
        $prices=$db->table((new \App\Models\Tenant\Catalogs\PriceType())->getTable())->get()->keyBy('id');
        $decimalFields=Persistence::columns(new \App\Models\Tenant\SaleNoteItem());$decoded=[];
        foreach (array_chunk($ids,500) as $batch) {
            $notes=SaleNote::withoutEagerLoads()->whereTypeUser()->whereIn('id',$batch)->orderBy('id')->get()->keyBy('id');
            if ($notes->count()!==count($batch)) abort(404);
            foreach ($notes as $note) {
                if (auth()->user()->type!=='admin' && (string)$note->establishment_id!==(string)auth()->user()->establishment_id) abort(403);
                if ($note->document_id || in_array($note->state_type_id,['09','11','13'],true)) self::invalid('Seleccione notas vigentes y pendientes de convertir.');
                $rate=\App\Services\ExchangeRates\ExchangeRateMath::rate($note->exchange_rate_sale,'exchange_rate_sale');
                $current=[$note->customer_id,$note->establishment_id,$note->currency_type_id,$rate];
                if ($context!==null && $context!==$current) self::invalid('Las notas deben conservar el mismo cliente, sucursal, moneda y tasa.');
                $context=$current;
            }
            foreach ($db->table('sale_note_items')->whereIn('sale_note_id',$batch)->orderBy('id')->lazyById(500) as $line) {
                $note=$notes[$line->sale_note_id];$row=(array)$line;
                foreach (['item','attributes','charges','discounts','calculation_snapshot'] as $field) {
                    if (isset($row[$field]) && is_string($row[$field])) {
                        $raw=$row[$field];$identity=$field."\0".$raw;
                        if (array_key_exists($identity,$decoded)) $row[$field]=$decoded[$identity];
                        else {
                            $value=self::storedJson($raw,$field==='calculation_snapshot');
                            $row[$field]=$field==='calculation_snapshot' ? $value : (object)$value;
                            if (count($decoded)<128) $decoded[$identity]=$row[$field];
                        }
                    }
                }
                $row['affectation_igv_type']=$affectations[$row['affectation_igv_type_id']] ?? null;
                $row['price_type']=$prices[$row['price_type_id']] ?? null;
                $transport=$selectAll ? $row : array_intersect_key($row,array_flip(['item_id','quantity','unit_price','item','calculation_snapshot']));
                foreach ($selectAll ? $decimalFields : ['quantity','unit_price'] as $field) if (isset($transport[$field])) $transport[$field]=Decimal::canonical($transport[$field]);
                $rows[]=$transport;
                $row['currency_type_id']=$note->currency_type_id;
                $row['exchange_rate_sale']=$context[3];
                $row['conversion_source']=['sale_note_id'=>$note->id,'sale_note_item_id'=>$line->id];
                yield $row;
            }
        }
    }

    public static function group(iterable $rows): array
    {
        $groups=[];$operands=[];$conditionKeys=[];
        foreach ($rows as $row) {
            if (is_string($row['calculation_snapshot'] ?? null)) $row['calculation_snapshot']=self::storedJson($row['calculation_snapshot'],true);
            if (is_object($row['calculation_snapshot'] ?? null)) $row['calculation_snapshot']=json_decode(json_encode($row['calculation_snapshot'],JSON_THROW_ON_ERROR),true,512,JSON_THROW_ON_ERROR);
            $snapshot=$row['calculation_snapshot'] ?? null;
            if ($snapshot!==null && (!is_array($snapshot) || ($snapshot['version'] ?? null)!==Decimal::VERSION || !is_array($snapshot['values'] ?? null))) Decimal::readRequired($row,'quantity');
            foreach (['quantity','unit_price','unit_value','percentage_igv','total_value','total_base_igv','total_igv',
                'total_taxes','total_discount','total_charge','total_other_taxes','total'] as $field) self::operand($row,$field,$operands);
            foreach (['discounts','charges'] as $kind) $row[$kind]=array_values((array)($row[$kind] ?? []));
            $quantity=self::operand($row,'quantity',$operands);
            if (!$quantity->isPositive()) self::invalid('La cantidad conservada debe ser positiva.');
            $price=self::operand($row,'unit_price',$operands);
            if ($price->isNegative()) self::invalid('El precio conservado no puede ser negativo.');
            $conditions=array_intersect_key($row,array_flip(['item_id','item','warehouse_id','affectation_igv_type_id',
                'price_type_id','attributes','additional_information','name_product_pdf','currency_type_id','exchange_rate_sale']));
            $conditions['item']=(array)($conditions['item'] ?? []);
            foreach (['stock','warehouses','current_stock','available_stock'] as $field) unset($conditions['item'][$field]);
            if (!empty($conditions['item']['lots']) || !empty($conditions['item']['IdLoteSelected'])) {
                $conditions['tracked_source']=$row['conversion_source'] ?? [$row['sale_note_id'] ?? null,$row['id'] ?? null];
            }
            foreach (['unit_price','unit_value','percentage_igv'] as $field) $conditions[$field]=self::normalizedFraction(self::operand($row,$field,$operands));
            foreach (['discounts','charges'] as $kind) {
                $conditions[$kind]=[];
                foreach ((array)($row[$kind] ?? []) as $adjustment) {
                    $adjustment=(array)$adjustment;
                    $fixed=!empty($adjustment['is_amount']) || !empty($adjustment['from_global_distribution']);
                    if ($fixed) $adjustment['amount_exact']=Decimal::fraction($adjustment['amount_exact'] ?? $adjustment['amount']);
                    else unset($adjustment['amount_exact']);
                    unset($adjustment['base'],$adjustment['amount'],$adjustment['amount_without_rounded']);
                    $conditions[$kind][]=$adjustment;
                }
            }
            $conditionIdentity=serialize($conditions);
            if (isset($conditionKeys[$conditionIdentity])) $key=$conditionKeys[$conditionIdentity];
            else {
                $key=json_encode(self::canonical($conditions),JSON_THROW_ON_ERROR);
                if (count($conditionKeys)<256) $conditionKeys[$conditionIdentity]=$key;
            }
            if (!isset($groups[$key])) {
                $base=$row;unset($base['id'],$base['sale_note_id'],$base['conversion_source']);
                foreach (['total_base_other_taxes','percentage_other_taxes'] as $field) if (isset($base[$field])) $base[$field]=Decimal::canonical($base[$field]);
                $base['calculation_snapshot']=Decimal::snapshot([],['conversion_sources'=>[]]);
                foreach (['unit_price','unit_value','percentage_igv'] as $field) $base['_values'][$field]=Decimal::of($conditions[$field]);
                $base['source_sale_note_items']=[];
                $groups[$key]=$base;
            }
            $group=&$groups[$key];
            foreach (self::ADDITIVE as $field) {
                if (!array_key_exists($field,$row) && !isset($row['calculation_snapshot']['values'][$field])) continue;
                $value=self::operand($row,$field,$operands);$sum=$group['_values'][$field] ?? Decimal::of(0);
                $group['_values'][$field]=$sum->getDenominator()->isEqualTo($value->getDenominator())
                    ? \Brick\Math\BigRational::nd($sum->getNumerator()->plus($value->getNumerator()),$sum->getDenominator())
                    : $sum->plus($value)->simplified();
            }
            if ($group['source_sale_note_items']) foreach (['discounts','charges'] as $kind) {
                $merged=(array)($group[$kind] ?? []);
                foreach ((array)($row[$kind] ?? []) as $index=>$adjustment) {
                    $a=(array)$adjustment;$previous=(array)$merged[$index];
                    foreach (['base','amount'] as $field) {
                        if (!isset($a[$field])) continue;
                        $amount=Decimal::of($previous[$field.'_exact'] ?? $previous[$field] ?? 0)->plus(Decimal::of($a[$field.'_exact'] ?? $a[$field]));
                        $previous[$field.'_exact']=$amount->simplified();
                    }
                    $merged[$index]=$previous;
                }
                $group[$kind]=array_values($merged);
            }
            $source=$row['conversion_source'] ?? ['sale_note_id'=>$row['sale_note_id'] ?? null,'sale_note_item_id'=>$row['id'] ?? null];
            $source['quantity']=self::normalizedFraction($quantity);$source['unit_price']=self::normalizedFraction($price);
            $group['source_sale_note_items'][]=$source;
            $group['calculation_snapshot']['inputs']['conversion_sources'][]=$source;
            unset($group);
        }
        foreach ($groups as &$group) {
            $group['calculation_snapshot']['values']=array_map([Decimal::class,'fraction'],$group['_values']);
            foreach ($group['_values'] as $field=>$value) $group[$field]=Decimal::represent($value);
            unset($group['_values']);
            foreach (['discounts','charges'] as $kind) {
                $adjustments=[];
                foreach ((array)($group[$kind] ?? []) as $a) {
                    $a=(array)$a;
                    foreach (['base','amount'] as $field) if (isset($a[$field.'_exact'])) {
                        $a[$field]=Decimal::represent($a[$field.'_exact']);$a[$field.'_exact']=Decimal::fraction($a[$field.'_exact']);
                    }
                    $adjustments[]=$a;
                }
                $group[$kind]=$adjustments;
            }
        }
        unset($group);
        return array_values($groups);
    }

    private static function canonical($value)
    {
        if (is_object($value)) $value=(array)$value;
        if (!is_array($value)) return $value;
        if (!array_is_list($value)) ksort($value);
        return array_map([self::class,'canonical'],$value);
    }

    /** Keep stored decimal tokens lexical, including legacy JSON without amount_exact. */
    private static function storedJson(string $json,bool $associative)
    {
        $exact=JsonDecimals::decode('['.$json.']')[0];
        if ($associative) return $exact;
        // Native decoding supplies container shapes only; its numeric values are never operands.
        $shape=json_decode($json,false,512,JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
        return self::restoreJsonShape($exact,$shape);
    }

    private static function restoreJsonShape($exact,$shape)
    {
        if (is_object($shape)) {
            $result=new \stdClass();
            foreach ($shape as $key=>$child) $result->{$key}=self::restoreJsonShape($exact[$key],$child);
            return $result;
        }
        if (is_array($shape)) {
            $result=[];
            foreach ($shape as $key=>$child) $result[$key]=self::restoreJsonShape($exact[$key],$child);
            return $result;
        }
        return $exact;
    }

    /** Intern already validated operands, bounded per execution; never cache a projection in place of n/d. */
    private static function operand(array $row,string $field,array &$cache): \Brick\Math\BigRational
    {
        $raw=$row['calculation_snapshot']['values'][$field] ?? $row[$field] ?? null;
        if ($raw===null) return Decimal::readRequired($row,$field);
        $key=serialize($raw);
        if (isset($cache[$key])) return $cache[$key];
        $value=Decimal::readRequired($row,$field)->simplified();
        if (count($cache)<256) $cache[$key]=$value;
        return $value;
    }

    private static function normalizedFraction(\Brick\Math\BigRational $value): array
    {
        return ['n'=>(string)$value->getNumerator(),'d'=>(string)$value->getDenominator()];
    }

    private static function invalid(string $message): void
    {
        throw ValidationException::withMessages(['notes_id'=>$message]);
    }
}
// ######## FIN CALCULOS EXACTOS PRO9 ########
