<?php

namespace Modules\Accounting\Models;

final class SourceResolution extends OwnedModel
{
    protected $casts = ['evidence' => 'array', 'base_revision' => 'integer', 'reviewed_by' => 'integer'];
}
