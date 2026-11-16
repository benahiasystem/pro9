<?php

namespace Modules\Accounting\Models;

final class SourceVersion extends OwnedModel
{
    protected $casts = ['snapshot' => 'array', 'document_snapshot' => 'array', 'revision' => 'integer', 'integrity_version' => 'integer', 'document_version' => 'integer', 'recorded_by' => 'integer'];
}
