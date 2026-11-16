<?php

namespace Modules\Accounting\Models;

final class RuleSet extends OwnedModel
{
    protected $casts = ['conditions' => 'array', 'rate_percent' => 'decimal:8', 'version' => 'integer', 'effective_from' => 'date:Y-m-d', 'effective_until' => 'date:Y-m-d', 'approved_at' => 'datetime'];
}
