<?php

namespace Modules\Accounting\Models;

final class Obligation extends OwnedModel
{
    protected $casts = ['amount' => 'decimal:8', 'due_date' => 'date:Y-m-d'];
}
