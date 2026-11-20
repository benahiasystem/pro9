<?php

namespace App\Support\Exact;

use App\Models\Tenant\{Document,DocumentItem,SaleNoteItem,Person};
use Illuminate\Database\Eloquent\Collection;

// ######## INICIO CALCULOS EXACTOS PRO9 ########
/** Exact, bounded rankings. Calendar/identity ties never depend on rounded money. */
final class DashboardRankings
{
    private static function grouped(ReportDataset $rows, string $key, array $fields): ReportDataset
    {
        $ordered=$rows->sortBy($key);
        return new ReportDataset((function () use ($ordered,$key,$fields) {
            $identity=null;$first=null;$values=array_fill_keys($fields,Decimal::of(0));
            $finish=static fn($first,$values)=>array_merge($first,Decimal::values($values),['calculation_snapshot'=>Decimal::snapshot($values)]);
            foreach ($ordered as $row) {
                if ($identity!==null && $identity!==$row[$key]) {
                    if ($values['total']->isPositive()) yield $finish($first,$values);
                    $values=array_fill_keys($fields,Decimal::of(0));
                }
                $identity=$row[$key];$first=$row;
                foreach ($values as $field=>$value) $values[$field]=$value->plus(Decimal::readRequired($row,$field))->simplified();
            }
            if ($first!==null && $values['total']->isPositive()) yield $finish($first,$values);
        })());
    }

    public static function customers($establishment, $start, $end, bool $byTransactions=false): array
    {
        return ReadSnapshot::run((new Document)->getConnection(),function () use ($establishment,$start,$end,$byTransactions) {
            $rows=new ReportDataset((function () use ($establishment,$start,$end) {
                foreach (['document'=>DashboardSales::documents($establishment,$start,$end),
                    'sale_note'=>DashboardSales::notes($establishment,$start,$end)] as $source=>$query) {
                    foreach ($query->lazyById(500) as $record) {
                        $amount=$source==='document' ? DashboardSales::documentAmount($record) : DashboardSales::inVes($record);
                        $count=$source==='document' && $record->document_type_id==='07' ? -1 : 1;
                        $values=['total'=>$amount,'transaction_quantity'=>Decimal::of($count)];
                        yield ['customer_id'=>(string)$record->customer_id]+Decimal::values($values)
                            + ['calculation_snapshot'=>Decimal::snapshot($values)];
                    }
                }
            })());
            $ordered=self::grouped($rows,'customer_id',['total','transaction_quantity'])
                ->sortExact($byTransactions ? 'transaction_quantity' : 'total',true);
            $top=$ordered->paginate(10,1)->items();
            $people=Person::withoutEagerLoads()->where('type','customers')->whereIn('id',array_column($top,'customer_id'))
                ->get(['id','name','number'])->keyBy('id');
            foreach ($top as &$row) {
                $person=$people->get($row['customer_id']);$row['name']=$person->name ?? '';$row['number']=$person->number ?? '';
                $row['total']=Decimal::format(Decimal::readRequired($row,'total'),2,'.','');
                $row['transaction_quantity']=Decimal::readRequired($row,'transaction_quantity')->toBigInteger()->toInt();
            }
            unset($row);return $top;
        });
    }

    public static function products($establishment, $start, $end, bool $byQuantity=false): ReportDataset
    {
        return ReadSnapshot::run((new DocumentItem)->getConnection(),function () use ($establishment,$start,$end,$byQuantity) {
            $rows=new ReportDataset((function () use ($establishment,$start,$end) {
                $reader=new ConsolidatedLines((new DocumentItem)->getConnection());
                foreach (['document','sale_note'] as $source) {
                    $query=$source==='document' ? DocumentItem::withoutEagerLoads() : SaleNoteItem::withoutEagerLoads();
                    $query->with([$source=>function ($parent) use ($source) {
                        $parent->withoutEagerLoads();if ($source==='document') $parent->with('note');
                    },'relation_item'=>fn($item)=>$item->withoutEagerLoads()]);
                    $query->whereHas('relation_item',fn($item)=>$item->where('status',true));
                    $query->whereHas($source,function ($parent) use ($source,$establishment,$start,$end) {
                        $parent->whereIn('state_type_id',DashboardSales::ACCEPTED_STATES);
                        if ($source==='document') $parent->whereIn('document_type_id',['01','07','08']);
                        else $parent->where('changed',false);
                        if ($establishment) $parent->where('establishment_id',$establishment);
                        if ($start && $end) $parent->whereBetween('date_of_issue',[$start,$end]);
                    });
                    foreach ($query->lazyById(500)->chunk(500) as $chunk) {
                        $chunk=new Collection($chunk->values()->all());$quantities=[];
                        foreach ($chunk as $line) {
                            $parent=$line->$source;$line->source_type=$source;$line->source_id=$parent->id;
                            $line->currency_type_id=$parent->currency_type_id;$line->exchange_rate_sale=DashboardSales::historicalRate($parent);
                            $line->document_type_id=$source==='document' ? $parent->document_type_id : '80';
                            $monetary=$line->document_type_id==='08' || ($line->document_type_id==='07'
                                && !in_array($parent->note->note_credit_type_id ?? null,['01','07'],true));
                            $quantity=$monetary ? Decimal::of(0) : Decimal::readRequired($line,'quantity')->multipliedBy(self::presentation($line));
                            if ($line->document_type_id==='07') $quantity=$quantity->negated();
                            $quantities[]=$quantity;
                        }
                        foreach ($reader->rows($chunk) as $position=>$row) {
                            $item=$chunk[$position]->relation_item;
                            $values=['total'=>Decimal::readRequired($row,'total_sale'),'move_quantity'=>$quantities[$position]];
                            yield ['item_id'=>(string)$item->id,'description'=>$item->description,'internal_id'=>$item->internal_id]
                                + Decimal::values($values)+['calculation_snapshot'=>Decimal::snapshot($values)];
                        }
                    }
                }
            })());
            return self::grouped($rows,'item_id',['total','move_quantity'])->sortExact($byQuantity ? 'move_quantity' : 'total',true)
                ->map(static function ($row) {
                    $row['total']=Decimal::format(Decimal::readRequired($row,'total'),2,'.','');
                    $row['move_quantity']=Decimal::represent(Decimal::readRequired($row,'move_quantity'));
                    return $row;
                });
        });
    }

    private static function presentation($line): \Brick\Math\BigRational
    {
        $item=$line->getRawOriginal('item');if (is_string($item)) $item=JsonDecimals::decode($item);
        $presentation=(array)(((array)$item)['presentation'] ?? []);
        $value=Decimal::inputRational($presentation['quantity_unit'] ?? '1','presentation.quantity_unit');
        if (!$value->isPositive()) throw new \UnexpectedValueException('La presentación requiere una cantidad positiva.');
        return $value;
    }
}
// ######## FIN CALCULOS EXACTOS PRO9 ########
