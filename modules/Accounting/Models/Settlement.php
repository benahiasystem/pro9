<?php

namespace Modules\Accounting\Models;

final class Settlement extends OwnedModel
{
    protected $casts = ['version' => 'integer'];
}
