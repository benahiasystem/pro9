<?php

namespace App\CoreFacturalo;

use Symfony\Component\HttpFoundation\Response;

/** Carry a rejected HTTP response out of the transaction without committing it. */
final class InputResponseRollback extends \RuntimeException
{
    public function __construct(public readonly Response $response)
    {
        parent::__construct('La operación documental fue rechazada.');
    }
}
