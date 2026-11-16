<?php

namespace Modules\Accounting\Models;

final class ImportUnit extends OwnedModel
{
    protected $casts = ['rows' => 'array', 'errors' => 'array', 'summary' => 'array', 'resource' => 'array', 'position' => 'integer'];
}
