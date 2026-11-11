<?php

namespace Modules\Accounting\Models;

final class TaxProfile extends OwnedModel
{
    protected $casts = ['version' => 'integer', 'details' => 'array', 'effective_from' => 'date:Y-m-d', 'effective_until' => 'date:Y-m-d'];
}
