<?php

namespace Modules\Accounting\Models;

final class YearClosing extends OwnedModel
{
    protected $casts = ['version' => 'integer', 'basis' => 'array', 'proposal' => 'array', 'entries' => 'array', 'posted_snapshot' => 'array', 'reopening_entries' => 'array', 'reopened_snapshot' => 'array', 'attestations' => 'array', 'exceptions' => 'array', 'posted_at' => 'datetime', 'reopened_at' => 'datetime'];
}
