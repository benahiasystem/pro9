<?php

namespace App\Support\Exact;

use App\Models\Tenant\{Document, SaleNote};

// ######## INICIO CALCULOS EXACTOS PRO9 ########
/** Documentary sales in VES. Fractions stay intact until an API/chart projection. */
final class DashboardSales
{
    public const ACCEPTED_STATES = ['01','03','05','07','13'];

    public static function documents($establishment, $start, $end)
    {
        return self::filter(Document::withoutEagerLoads(),$establishment,$start,$end)
            ->whereIn('state_type_id',self::ACCEPTED_STATES)->whereIn('document_type_id',['01','07','08']);
    }

    public static function notes($establishment, $start, $end)
    {
        return self::filter(SaleNote::withoutEagerLoads(),$establishment,$start,$end)
            ->where('changed',false)->whereStateTypeAccepted();
    }

    private static function filter($query, $establishment, $start, $end)
    {
        return $query->when($establishment,fn ($q)=>$q->where('establishment_id',$establishment))
            ->when($start && $end,fn ($q)=>$q->whereBetween('date_of_issue',[$start,$end]));
    }

    public static function inVes($record, string $field='total'): \Brick\Math\BigRational
    {
        return self::inVesAmount($record,Decimal::readRequired($record,$field));
    }

    public static function inVesAmount($record, $amount): \Brick\Math\BigRational
    {
        $currency=$record->currency_type_id;
        $rate=$currency === 'USD' ? self::historicalRate($record) : '1';
        return Decimal::convert($amount,$currency,'VES',$rate);
    }

    public static function historicalRate($record): string
    {
        try {
            $value=(string)Decimal::readRequired($record,'exchange_rate_sale')->toScale(8,\Brick\Math\RoundingMode::UNNECESSARY);
            return \App\Services\ExchangeRates\ExchangeRateMath::rate($value);
        } catch (\Brick\Math\Exception\MathException $error) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'exchange_rate_sale'=>'La tasa histórica requiere ocho posiciones exactas, sin redondeo.']);
        }
    }

    public static function documentAmount($record): \Brick\Math\BigRational
    {
        if (!in_array($record->state_type_id,self::ACCEPTED_STATES,true)
            || !in_array($record->document_type_id,['01','07','08'],true)) return Decimal::of(0);
        $amount=self::inVes($record);
        return $record->document_type_id === '07' ? $amount->negated() : $amount;
    }

    public static function totals(iterable $documents, iterable $notes): array
    {
        $documentTotal=$noteTotal=Decimal::of(0);
        foreach ($documents as $document) $documentTotal=$documentTotal->plus(self::documentAmount($document))->simplified();
        foreach ($notes as $note) {
            if ($note->changed || !in_array($note->state_type_id,self::ACCEPTED_STATES,true)) continue;
            $noteTotal=$noteTotal->plus(self::inVes($note))->simplified();
        }
        return ['documents_total'=>$documentTotal,'sale_notes_total'=>$noteTotal,'total'=>$documentTotal->plus($noteTotal)->simplified()];
    }

    public static function range($establishment, $start, $end): array
    {
        return ReadSnapshot::run((new Document)->getConnection(),fn ()=>self::totals(
            self::documents($establishment,$start,$end)->lazyById(500),
            self::notes($establishment,$start,$end)->lazyById(500)));
    }

    public static function sourceTotal(string $source, $establishment, $start, $end): \Brick\Math\BigRational
    {
        return ReadSnapshot::run((new Document)->getConnection(),function () use ($source,$establishment,$start,$end) {
            $query=match ($source) {
                'document'=>self::documents($establishment,$start,$end),
                'sale_note'=>self::notes($establishment,$start,$end),
                default=>throw new \InvalidArgumentException('Origen de ventas no soportado.'),
            };
            $sum=Decimal::of(0);
            foreach ($query->lazyById(500) as $record) {
                $sum=$sum->plus($source === 'document' ? self::documentAmount($record) : self::inVes($record))->simplified();
            }
            return $sum;
        });
    }

    /** Materialize just the chart operands under the caller's consistent read. */
    public static function dataset($establishment, $start, $end): ReportDataset
    {
        return ReadSnapshot::run((new Document)->getConnection(),function () use ($establishment,$start,$end) {
            return new ReportDataset((function () use ($establishment,$start,$end) {
                $fields=['id','state_type_id','currency_type_id','total','exchange_rate_sale',
                    'calculation_snapshot','date_of_issue','time_of_issue'];
                foreach (['document'=>self::documents($establishment,$start,$end)->select(array_merge($fields,['document_type_id'])),
                    'sale_note'=>self::notes($establishment,$start,$end)->select(array_merge($fields,['changed']))] as $source=>$query) {
                    foreach ($query->lazyById(500) as $record) {
                        $amount=$source === 'document' ? self::documentAmount($record) : self::inVes($record);
                        yield ['source'=>$source,'date_of_issue'=>(string)$record->getRawOriginal('date_of_issue'),
                            'time_of_issue'=>(string)$record->getRawOriginal('time_of_issue'),
                            'amount'=>Decimal::represent($amount),'calculation_snapshot'=>Decimal::snapshot(['amount'=>$amount])];
                    }
                }
            })());
        });
    }

    public static function datasetTotals(ReportDataset $rows): array
    {
        $documents=$notes=Decimal::of(0);
        foreach ($rows as $row) {
            $amount=Decimal::readRequired($row,'amount');
            if ($row['source'] === 'document') $documents=$documents->plus($amount)->simplified();
            else $notes=$notes->plus($amount)->simplified();
        }
        return ['documents_total'=>$documents,'sale_notes_total'=>$notes,'total'=>$documents->plus($notes)->simplified()];
    }

    public static function chart(ReportDataset $rows, array $buckets, string $period): array
    {
        if (!in_array($period,['hour','day','month'],true)) throw new \InvalidArgumentException('Agrupación no soportada.');
        $totals=$documents=$notes=array_fill_keys(array_keys($buckets),Decimal::of(0));
        foreach ($rows as $row) {
            $key=$period === 'hour' ? substr($row['time_of_issue'],0,2)
                : ($period === 'month' ? substr($row['date_of_issue'],0,7) : $row['date_of_issue']);
            if (!array_key_exists($key,$buckets)) continue;
            $amount=Decimal::readRequired($row,'amount');
            if ($row['source'] === 'document') $documents[$key]=$documents[$key]->plus($amount)->simplified();
            else $notes[$key]=$notes[$key]->plus($amount)->simplified();
            $totals[$key]=$totals[$key]->plus($amount)->simplified();
        }
        $result=[];
        foreach (['sale_notes_array'=>$notes,'documents_array'=>$documents,'total_array'=>$totals] as $name=>$values) {
            $text=[]; $labelled=[];
            foreach ($buckets as $key=>$label) {
                $text[$label]=Decimal::format($values[$key],2,'.',''); $labelled[$label]=$values[$key];
            }
            // Final chart coordinates only; no server calculation consumes these floats.
            $result[$name]=array_map(static fn($value)=>(float)$value,$text);
            $result[$name.'_exact']=$text;
            $result[$name.'_snapshot']=Decimal::snapshot($labelled);
        }
        return $result;
    }

    /** Preserve the existing numeric API fields, with exact text and fractions alongside. */
    public static function numericProjection(array $values): array
    {
        $result=[];
        foreach ($values as $field=>$value) {
            $text=Decimal::format($value,2,'.','');
            $result[$field]=(float)$text;
            $result[$field.'_exact']=$text;
        }
        $result['calculation_snapshot']=Decimal::snapshot($values);
        return $result;
    }
}
// ######## FIN CALCULOS EXACTOS PRO9 ########
