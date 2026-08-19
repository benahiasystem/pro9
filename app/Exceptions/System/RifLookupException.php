<?php

namespace App\Exceptions\System;

use RuntimeException;

// ########## INICIO CAMBIO RIF SUPER ADMIN
class RifLookupException extends RuntimeException
{
    private int $responseStatus;

    public function __construct(string $message, int $responseStatus = 502)
    {
        parent::__construct($message);
        $this->responseStatus = $responseStatus;
    }

    public function responseStatus(): int
    {
        return $this->responseStatus;
    }
}
// ######### FIN CAMBIO RIF SUPER ADMIN
