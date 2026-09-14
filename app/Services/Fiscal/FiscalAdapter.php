<?php

namespace App\Services\Fiscal;

// ######## INICIO NUMERACIÓN FISCAL VENEZUELA ########
interface FiscalAdapter
{
    /** Send the same operation key on every provider call. */
    public function emit(object $reservation, array $snapshot): array;

    /** Return issued, rejected, uncertain or a verified not_found result. */
    public function lookup(object $reservation, array $snapshot): array;
}
// ######## FIN NUMERACIÓN FISCAL VENEZUELA ########
