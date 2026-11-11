<?php

namespace Modules\Accounting\Models;

final class SourceVersion extends OwnedModel
{
    protected $casts = ['snapshot' => 'array', 'revision' => 'integer'];
}
