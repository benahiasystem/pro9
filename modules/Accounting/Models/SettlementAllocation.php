<?php

namespace Modules\Accounting\Models;

final class SettlementAllocation extends OwnedModel
{
    protected $casts = ['original_amount' => 'decimal:8', 'book_amount' => 'decimal:8', 'settlement_amount' => 'decimal:8', 'exchange_difference' => 'decimal:8'];
}
