<?php

namespace Modules\Accounting\Models;

final class OpenItem extends OwnedModel
{
    protected $casts = ['original_amount' => 'decimal:8', 'book_amount' => 'decimal:8', 'remaining_original' => 'decimal:8', 'remaining_book' => 'decimal:8', 'is_opening' => 'boolean'];
}
