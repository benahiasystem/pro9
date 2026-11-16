<?php

namespace Modules\Accounting\Models;

final class Incident extends OwnedModel
{
    protected $casts = ['details' => 'array', 'version' => 'integer', 'resolved_at' => 'datetime'];
}
