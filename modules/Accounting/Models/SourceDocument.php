<?php

namespace Modules\Accounting\Models;

final class SourceDocument extends OwnedModel
{
    protected $casts = ['validated_version' => 'integer', 'validated_at' => 'datetime', 'amounts' => 'array', 'fiscal_amounts' => 'array', 'tax_components' => 'array', 'issues' => 'array', 'metadata' => 'array', 'source_revision' => 'integer', 'version' => 'integer', 'exchange_rate' => 'decimal:12', 'issue_date' => 'date:Y-m-d', 'fiscal_date' => 'date:Y-m-d'];
}
