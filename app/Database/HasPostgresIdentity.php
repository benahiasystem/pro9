<?php
namespace App\Database;

// ########## INICIO CAMBIO POSTGRESQL 17
trait HasPostgresIdentity
{
    public function getAttributeValue($key)
    {
        return IntegerTransport::value(parent::getAttributeValue($key));
    }

    public function attributesToArray()
    {
        return array_map([IntegerTransport::class,'value'],parent::attributesToArray());
    }

    protected function performInsert(\Illuminate\Database\Eloquent\Builder $query)
    {
        $key=$this->getKeyName();
        if ($this->getIncrementing() && $this->getConnection()->getDriverName()==='pgsql' && array_key_exists($key,$this->attributes) && $this->attributes[$key]===null) unset($this->attributes[$key]);
        return parent::performInsert($query);
    }
}
// ######### FIN CAMBIO POSTGRESQL 17
