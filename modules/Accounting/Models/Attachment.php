<?php

namespace Modules\Accounting\Models;

final class Attachment extends OwnedModel
{
    protected $casts = ['size' => 'integer', 'resource_version' => 'integer', 'after_posting' => 'boolean', 'resource_snapshot' => 'array'];
}
