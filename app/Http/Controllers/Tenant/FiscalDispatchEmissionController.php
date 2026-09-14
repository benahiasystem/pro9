<?php

namespace App\Http\Controllers\Tenant;

// ######## INICIO NUMERACIÓN FISCAL VENEZUELA ########
class FiscalDispatchEmissionController extends FiscalDocumentEmissionController
{
    protected string $subjectTable = 'dispatches';
    protected string $reservationColumn = 'dispatch_id';
}
// ######## FIN NUMERACIÓN FISCAL VENEZUELA ########
