<?php

namespace App\Models\Tenant\Traits;

use App\Services\Fiscal\FiscalIdentity;
use App\Services\FiscalControlNumber;
use Illuminate\Database\Eloquent\Builder;

trait HasFiscalIdentity
{
    public function getFiscalIdentityAttribute(): array
    {
        return FiscalIdentity::forDocument($this);
    }

    public function scopeWhereFiscalIdentifiers(Builder $query, $series = null, $number = null, $control = null): Builder
    {
        if ($series !== null && $series !== '') $query->where($this->qualifyColumn('series'), 'like', '%' . $series . '%');
        if ($number !== null && $number !== '') $query->where($this->qualifyColumn('number'), $number);
        if ($control !== null && $control !== '') {
            try {
                if (!is_string($control)) throw new \InvalidArgumentException('Indique un número de control válido.');
                $control = (string) new FiscalControlNumber($control);
            } catch (\InvalidArgumentException $exception) {
                throw \Illuminate\Validation\ValidationException::withMessages(['control_number' => $exception->getMessage()]);
            }
            $query->where($this->qualifyColumn('control_number'), $control);
        }
        return $query;
    }
}
