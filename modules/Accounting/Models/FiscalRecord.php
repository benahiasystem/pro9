<?php

namespace Modules\Accounting\Models;

final class FiscalRecord extends OwnedModel
{
    protected $casts = ['amounts_ves' => 'array', 'tax_components' => 'array', 'rule_snapshot' => 'array', 'event_date' => 'date:Y-m-d', 'version' => 'integer'];
}
