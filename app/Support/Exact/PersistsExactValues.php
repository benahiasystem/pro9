<?php

namespace App\Support\Exact;

// ######## INICIO CALCULOS EXACTOS PRO9 ########
trait PersistsExactValues
{
    public function save(array $options = [])
    {
        $save=function () use ($options) {
            Persistence::prepare($this);
            return parent::save($options);
        };
        return Persistence::enabled($this) && $this->getConnection()->transactionLevel()===0
            ? $this->getConnection()->transaction($save) : $save();
    }
}
// ######## FIN CALCULOS EXACTOS PRO9 ########
