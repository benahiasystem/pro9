<?php

namespace App\Models\Tenant\Traits;

use App\Models\Tenant\FiscalNumberReservation;
use App\Services\Fiscal\FiscalIdentity;
use App\Services\FiscalControlNumber;
use Illuminate\Database\Eloquent\Builder;

// ######## INICIO NUMERACIÓN FISCAL VENEZUELA ########
trait HasFiscalIdentity
{
    public function fiscalReservation()
    {
        return $this->hasOne(FiscalNumberReservation::class, $this->getTable() === 'dispatches' ? 'dispatch_id' : 'document_id')->with('replacement');
    }

    public function getFiscalIdentityAttribute(): array
    {
        return FiscalIdentity::forDocument($this);
    }

    public function scopeWhereFiscalIdentifiers(Builder $query, $series = null, $number = null, $control = null): Builder
    {
        if (($series === null || $series === '') && ($number === null || $number === '') && ($control === null || $control === '')) return $query;
        if ($control !== null && $control !== '') {
            try {
                if (!is_string($control)) throw new \InvalidArgumentException('Indique un número de control válido.');
                $control = (string) new FiscalControlNumber($control);
            } catch (\InvalidArgumentException $exception) {
                throw \Illuminate\Validation\ValidationException::withMessages(['control_number' => $exception->getMessage()]);
            }
        } else { $control = null; }
        $match = static function (Builder $reservation) use ($series, $number, $control) {
            if ($series !== null && $series !== '') $reservation->whereHas('sequence', fn (Builder $sequence) => $sequence->where('series_code', 'like', '%' . $series . '%'));
            if ($number !== null && $number !== '') $reservation->where('document_number', $number);
            if ($control !== null) $reservation->where('control_number', $control);
        };
        $rootIds = FiscalIdentity::matchingRootIds($match);
        return $query->where(function (Builder $documents) use ($series, $number, $control, $rootIds) {
            $documents->whereHas('fiscalReservation', fn (Builder $root) => $root->whereIn('id', $rootIds));
            if ($control === null) {
                $documents->orWhere(function (Builder $commercial) use ($series, $number) {
                    $commercial->whereDoesntHave('fiscalReservation');
                    if ($series !== null && $series !== '') $commercial->where($commercial->getModel()->qualifyColumn('series'), 'like', '%' . $series . '%');
                    if ($number !== null && $number !== '') $commercial->where($commercial->getModel()->qualifyColumn('number'), $number);
                });
            }
        });
    }
}
// ######## FIN NUMERACIÓN FISCAL VENEZUELA ########
