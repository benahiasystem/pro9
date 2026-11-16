<?php

namespace Modules\Accounting\Models;

final class OpeningBatch extends OwnedModel
{
    protected $casts = ['version' => 'integer', 'cutoff_date' => 'date:Y-m-d'];
}
