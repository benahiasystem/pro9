<?php

namespace App\Http\Resources\Tenant;

use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Carbon;
use Modules\Finance\Models\Income;
use Modules\Pos\Http\Controllers\CashController;

class CashCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return mixed
     */
    public function toArray($request)
    {
        return $this->collection->transform(function($row, $key) {

            // ######## INICIO CLASIFICACION DE COBROS Y CAJA ########
            $balances=\App\Services\Payments\CashLedger::report($row->resource)['balances_by_currency'];
            // ######## FIN CLASIFICACION DE COBROS Y CAJA ########
            return [
                'id' => $row->id,
                'user_id' => $row->user_id,
                'user' => $row->user->name,
                'date_opening' => $row->date_opening,
                'time_opening' => $row->time_opening,
                'opening' => "{$row->date_opening} {$row->time_opening}",
                'date_closed' => $row->date_closed,
                'time_closed' => $row->time_closed, 
                'closed' => "{$row->date_closed} {$row->time_closed}",
                // ######## INICIO CLASIFICACION DE COBROS Y CAJA ########
                'report_version' => 2,
                'balances_by_currency' => $balances,
                // ######## FIN CLASIFICACION DE COBROS Y CAJA ########
                'beginning_balance' => $balances['VES']['beginning_balance'],
                'final_balance' => $balances['VES']['final_balance'],
                'income' => \App\Support\Exact\Decimal::represent(\App\Support\Exact\Decimal::readRequired($balances['VES'],'income')->minus(\App\Support\Exact\Decimal::readRequired($balances['VES'],'egress'))),
                'expense' => $row->expense,
                'filename' => $row->filename,
                'state' => (bool) $row->state, 
                'state_description' => ($row->state) ? 'Aperturada':'Cerrada',
                'reference_number' => $row->reference_number,
                'user_email' => optional($row->user)->email,
            ];
        });
    }
}
