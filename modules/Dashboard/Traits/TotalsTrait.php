<?php
// ######## INICIO TASAS OCHO DECIMALES: CONVERSIONES EXACTAS ########
// ######## FIN TASAS OCHO DECIMALES: CONVERSIONES EXACTAS ########
// ######## INICIO MIGRACIÓN MONEDA VENEZUELA ########

namespace Modules\Dashboard\Traits;

trait TotalsTrait
{

    /**
     *
     * Filtra por sucursal solo cuando se recibe una; sin valor (opción "Todos")
     * la consulta abarca todos los establecimientos.
     *
     * @param  \Illuminate\Database\Eloquent\Builder $query
     * @param  int|null $establishment_id
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function filterEstablishment($query, $establishment_id)
    {
        if ($establishment_id) {
            $query->where('establishment_id', $establishment_id);
        }

        return $query;
    }

    public function get_purchase_totals($establishment_id, $date_start, $date_end)
    {
        return \App\Support\Exact\DashboardTotals::range('purchase',$establishment_id,$date_start,$date_end);
    }


    public function get_expense_totals($establishment_id, $date_start, $date_end)
    {
        return \App\Support\Exact\DashboardTotals::range('expense',$establishment_id,$date_start,$date_end);
    }


    public function get_sale_note_totals($establishment_id, $date_start, $date_end)
    {
        return \App\Support\Exact\DashboardTotals::range('sale_note',$establishment_id,$date_start,$date_end);
    }


    public function get_document_totals($establishment_id, $date_start, $date_end)
    {
        return \App\Support\Exact\DashboardTotals::range('document',$establishment_id,$date_start,$date_end);
    }


    /**
     *
     * Obtener suma total de pedidos
     *
     * @param  int $establishment_id
     * @param  string $date_start
     * @param  string $date_end
     * @return string
     */
    public function getTotalsOrderNote($establishment_id, $date_start, $date_end)
    {
        return \App\Support\Exact\Decimal::represent(\App\Support\Exact\DashboardTotals::orderNotes($establishment_id,$date_start,$date_end));
    }


    /**
     * Redondear número
     *
     * @param  string $value
     * @param  int $decimals
     * @return string
     */
    public function roundNumber($value, $decimals = 2)
    {
        return \App\Support\Exact\Decimal::format($value,$decimals,'.','');
    }

}

// ######## FIN MIGRACIÓN MONEDA VENEZUELA ########
