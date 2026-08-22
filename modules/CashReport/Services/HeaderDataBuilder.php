<?php

namespace Modules\CashReport\Services;

use App\Models\Tenant\Company;
use App\Models\Tenant\Cash;

/**
 * Datos comunes de cabecera para todos los reportes de caja.
 */
class HeaderDataBuilder
{
    public static function build(Cash $cash): array
    {
        $company = Company::select('name', 'number')->first();
        $establishment = $cash->user->establishment;

        return [
            'company_name' => $company->name,
            'company_number' => $company->number,
            'establishment_address' => $establishment->address,
            'establishment_department_description' => optional($establishment->department)->description,
            'establishment_district_description' => optional($establishment->district)->description,
            'cash_user_name' => $cash->user->name,
            'cash_state' => $cash->state,
            'cash_date_opening' => $cash->date_opening,
            'cash_time_opening' => $cash->time_opening,
            'cash_date_closed' => $cash->date_closed,
            'cash_time_closed' => $cash->time_closed,
            'cash_beginning_balance' => $cash->beginning_balance,
            'total_income' => 0,
            'total_egress' => 0,
            'printed_by' => optional(auth()->user())->name,
            'printed_at' => date('d/m/Y H:i'),
        ];
    }
}
