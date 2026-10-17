<?php

namespace App\Support\Exact;

// ######## INICIO CALCULOS EXACTOS PRO9 ########
/** Shared decimal transport for central and tenant models; no connection switching. */
trait HasExactValues
{
    protected function fireModelEvent($event, $halt = true)
    {
        $result = parent::fireModelEvent($event, $halt);
        if ($result !== false && in_array($event, ['creating', 'updating'], true)) Persistence::prepareValues($this);
        return $result;
    }

    protected function exactCasts(array $casts): array
    {
        $casts['calculation_snapshot']='array';
        foreach (Persistence::columns($this) as $field) $casts[$field]='string';
        return $casts;
    }

    public function getCasts()
    {
        return $this->exactCasts(parent::getCasts());
    }

    public function setAttribute($key, $value)
    {
        if ($value instanceof \Brick\Math\BigRational) {
            $snapshot=$this->calculation_snapshot ?? Decimal::snapshot([]);
            $snapshot['values'][$key]=Decimal::fraction($value);
            parent::setAttribute('calculation_snapshot',$snapshot);
            $value=Decimal::represent($value);
        } elseif (in_array($key,Persistence::columns($this),true)) {
            $snapshot=$this->calculation_snapshot;
            if (isset($snapshot['values'][$key])) {
                try {
                    $same=$value!==null && Decimal::of($value)->isEqualTo(Decimal::represent($snapshot['values'][$key]));
                } catch (\Throwable $error) {$same=false;}
                if (!$same) {
                    // An edited scalar is the new input even before the next save.
                    unset($snapshot['values'][$key]);
                    parent::setAttribute('calculation_snapshot',$snapshot);
                }
            }
        }
        return parent::setAttribute($key,$value);
    }

    protected function castAttribute($key, $value)
    {
        if ($value!==null && in_array($key,Persistence::columns($this),true)) return Decimal::canonical($value);
        return parent::castAttribute($key,$value);
    }

    public function exactValue(string $field): \Brick\Math\BigRational
    {
        return Decimal::read($this,$field);
    }

    public function setExactValues(array $values): self
    {
        $snapshot=$this->calculation_snapshot ?? Decimal::snapshot([]);
        foreach ($values as $field=>$value) {
            $snapshot['values'][$field]=Decimal::fraction($value);
            $this->setAttribute($field,Decimal::represent($value));
        }
        $this->calculation_snapshot=$snapshot;
        return $this;
    }
}
// ######## FIN CALCULOS EXACTOS PRO9 ########
