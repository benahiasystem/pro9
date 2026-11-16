<?php

namespace Modules\Accounting\Models;

final class Account extends OwnedModel
{
    protected $casts = ['posting' => 'boolean', 'active' => 'boolean', 'requires_party' => 'boolean', 'version' => 'integer'];
}
