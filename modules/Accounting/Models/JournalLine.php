<?php

namespace Modules\Accounting\Models;

final class JournalLine extends OwnedModel
{
    protected $casts = ['debit' => 'decimal:8', 'credit' => 'decimal:8', 'original_amount' => 'decimal:12', 'exchange_rate' => 'decimal:12'];
}
