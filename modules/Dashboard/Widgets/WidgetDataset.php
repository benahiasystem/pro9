<?php

namespace Modules\Dashboard\Widgets;

use App\Support\Exact\Decimal as D;

// ######## INICIO CALCULOS EXACTOS SAB ########
/** Numeric coordinates are final projections; text and original fractions travel with them. */
class WidgetDataset
{
    const UNIT_MONEY='money';
    const UNIT_COUNT='count';
    const UNIT_PERCENT='percent';

    private $unit;
    private $labels=[];
    private $series=[];
    private $breakdown=['labels'=>[],'values'=>[],'values_exact'=>[],'values_fractions'=>[]];
    private $totals;
    private $meta=[];

    public function __construct($unit=self::UNIT_MONEY)
    {
        if (!in_array($unit,[self::UNIT_MONEY,self::UNIT_COUNT,self::UNIT_PERCENT],true)) throw new \InvalidArgumentException('Unidad de widget inválida.');
        $this->unit=$unit;$this->totals(null);
    }

    public static function make($unit=self::UNIT_MONEY) {return new static($unit);}

    private static function amount($value): \Brick\Math\BigRational
    {
        if (is_float($value) || is_bool($value) || $value===null || $value==='') {
            throw new \UnexpectedValueException('El widget requiere el operando original, no una proyección binaria o ausente.');
        }
        try {return D::of($value);}
        catch (\Throwable $error) {throw new \UnexpectedValueException('Operando original del widget inválido.',0,$error);}
    }

    private function text($value): string
    {
        return $this->unit===self::UNIT_COUNT ? D::represent($value)
            : D::format($value,$this->unit===self::UNIT_PERCENT ? 1 : 2,'.','');
    }

    private function project(array $values): array
    {
        $data=$exact=$fractions=[];
        foreach ($values as $value) {
            if ($value===null) {$data[]=null;$exact[]=null;$fractions[]=null;continue;}
            $value=self::amount($value);$text=$this->text($value);
            $data[]=(float)$text;$exact[]=$text;$fractions[]=D::fraction($value);
        }
        return ['data'=>$data,'exact'=>$exact,'fractions'=>$fractions];
    }

    public function serie(array $labels, array $series)
    {
        $projected=[];
        foreach ($series as $serie) {
            $values=array_values($serie['data'] ?? []);
            if (count($values)!==count($labels)) throw new \InvalidArgumentException('Las etiquetas y puntos del widget no coinciden.');
            $projection=$this->project($values);
            $projected[]=['name'=>(string)($serie['name'] ?? ''),'data'=>$projection['data'],
                'data_exact'=>$projection['exact'],'data_fractions'=>$projection['fractions']];
        }
        $this->labels=array_values($labels);$this->series=$projected;return $this;
    }

    public function breakdown(array $labels, array $values)
    {
        if (count($labels)!==count($values)) throw new \InvalidArgumentException('Las categorías y valores del widget no coinciden.');
        $projection=$this->project(array_values($values));
        $this->breakdown=['labels'=>array_map('strval',array_values($labels)),'values'=>$projection['data'],
            'values_exact'=>$projection['exact'],'values_fractions'=>$projection['fractions']];
        return $this;
    }

    public function totals($current, $previous=null)
    {
        $current=$current===null ? null : self::amount($current);$previous=$previous===null ? null : self::amount($previous);
        $values=[];
        if ($current!==null) $values['current']=$current;
        if ($previous!==null) $values['previous']=$previous;
        $delta=null;
        if ($current!==null && $previous!==null) {
            $delta=$previous->isZero() ? D::of($current->isPositive() ? 100 : 0)
                : $current->minus($previous)->dividedBy($previous)->multipliedBy(100)->simplified();
            $values['delta']=$delta;
        }
        $currentText=$current===null ? null : $this->text($current);$previousText=$previous===null ? null : $this->text($previous);
        $deltaText=$delta===null ? null : D::format($delta,1,'.','');
        $this->totals=['current'=>$currentText===null ? null : (float)$currentText,
            'previous'=>$previousText===null ? null : (float)$previousText,'delta'=>$deltaText===null ? null : (float)$deltaText,
            'current_exact'=>$currentText,'previous_exact'=>$previousText,'delta_exact'=>$deltaText,
            'calculation_snapshot'=>D::snapshot($values)];
        return $this;
    }

    public function meta(array $meta) {$this->meta=array_merge($this->meta,$meta);return $this;}

    public function toArray()
    {
        return ['unit'=>$this->unit,'labels'=>$this->labels,'series'=>$this->series,
            'breakdown'=>$this->breakdown,'totals'=>$this->totals,'meta'=>$this->meta];
    }
}
// ######## FIN CALCULOS EXACTOS SAB ########
