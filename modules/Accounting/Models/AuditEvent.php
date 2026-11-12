<?php

namespace Modules\Accounting\Models;

final class AuditEvent extends OwnedModel
{
    protected $casts = ['payload' => 'array', 'sequence' => 'integer', 'hash_version' => 'integer'];
}
