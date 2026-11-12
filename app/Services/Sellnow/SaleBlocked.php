<?php

namespace App\Services\Sellnow;

final class SaleBlocked extends \RuntimeException
{
    public function __construct(public string $reason, string $message)
    {
        parent::__construct($message);
    }
}
