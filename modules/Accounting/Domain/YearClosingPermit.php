<?php

namespace Modules\Accounting\Domain;

/** Internal capability: HTTP inputs never construct or supply this parameter. */
final class YearClosingPermit
{
    public function __construct(public readonly string $closingId, public readonly string $direction)
    {
        if (!in_array($direction, ['close', 'reopen'], true)) {
            throw new \LogicException('Dirección de cierre anual desconocida.');
        }
    }
}
