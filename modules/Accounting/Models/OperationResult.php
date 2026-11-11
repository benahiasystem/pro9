<?php

namespace Modules\Accounting\Models;

final class OperationResult extends OwnedModel
{
    protected $casts = ['result' => 'array'];
}
