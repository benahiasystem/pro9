<?php

namespace App\Models\Tenant;

use Hyn\Tenancy\Traits\UsesTenantConnection;

class ExchangeRate extends ModelTenant
{
    use UsesTenantConnection;

    // ######## INICIO API BCV ########
    protected $primaryKey = 'date';
    public $incrementing = false;
    protected $keyType = 'string';
    // ######## FIN API BCV ########

    protected $fillable = [
        'date',
        'date_original',
        'purchase',
        'purchase_original',
        'sale',
        'sale_original',
    ];

    // ######## INICIO TASAS OCHO DECIMALES ########
    public function save(array $options = [])
    {
        foreach (['purchase', 'purchase_original', 'sale', 'sale_original'] as $column) {
            if (array_key_exists($column, $this->attributes)) {
                $this->attributes[$column] = \App\Services\ExchangeRates\ExchangeRateMath::rate($this->attributes[$column], $column);
            }
        }
        return parent::save($options);
    }
    // ######## FIN TASAS OCHO DECIMALES ########

    protected $casts = [
        'purchase' => 'decimal:8',
        'purchase_original' => 'decimal:8',
        'sale' => 'decimal:8',
        'sale_original' => 'decimal:8',
    ];
}
