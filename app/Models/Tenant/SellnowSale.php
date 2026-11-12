<?php

namespace App\Models\Tenant;

class SellnowSale extends ModelTenant
{
    protected $table = 'sellnow_sales';
    protected $guarded = [];
    protected $casts = [
        'envelope' => 'array', 'result' => 'array', 'error' => 'array',
        'effects' => 'array', 'cash_history' => 'array',
    ];
}
