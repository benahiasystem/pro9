<?php

namespace Modules\Accounting\Models;

final class ImportReceipt extends OwnedModel
{
    protected $casts = ['resource' => 'array'];

    protected static function booted(): void
    {
        parent::booted();
        static::updating(fn() => throw new \LogicException('La aceptación de una importación es inmutable.'));
        static::deleting(fn() => throw new \LogicException('Conserva la identidad de la importación aceptada.'));
    }
}
