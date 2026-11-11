<?php

namespace Modules\Accounting\Models;

final class ImportJob extends OwnedModel
{
    protected $casts = ['result' => 'array', 'errors' => 'array'];
}
