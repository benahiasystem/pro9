<?php

namespace Modules\Accounting\Models;

final class FiscalYear extends OwnedModel
{
    protected $casts = ['year' => 'integer'];
}
