<?php

namespace App\Http\Resources\Tenant;

use Illuminate\Http\Resources\Json\JsonResource;

class CashResource extends JsonResource
{
    /**
     * Transform the resource collection into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return mixed
     */
    public function toArray($request)
    {
            $balances=\App\Services\Payments\CashLedger::report($this->resource)['balances_by_currency'];
            return [
                'id' => $this->id,
                'user_id' => $this->user_id,
                'user' => $this->user->name,
                'date_opening' => $this->date_opening,
                'time_opening' => $this->time_opening,
                'opening' => "{$this->date_opening} {$this->time_opening}",
                'date_closed' => $this->date_closed,
                'time_closed' => $this->time_closed, 
                'closed' => "{$this->date_closed} {$this->time_closed}",
                // ######## INICIO CLASIFICACION DE COBROS Y CAJA ########
                'report_version' => 2,
                'balances_by_currency' => $balances,
                // ######## FIN CLASIFICACION DE COBROS Y CAJA ########
                'beginning_balance' => $balances['VES']['beginning_balance'],
                'final_balance' => $balances['VES']['final_balance'],
                'income' => \App\Support\Exact\Decimal::represent(\App\Support\Exact\Decimal::readRequired($balances['VES'],'income')->minus(\App\Support\Exact\Decimal::readRequired($balances['VES'],'egress'))),
                'expense' => $this->expense,
                'filename' => $this->filename,
                'state_description' => ($this->state) ? 'Aperturada':'Cerrada', 
                'state' => (bool) $this->state,
                'reference_number' => $this->reference_number
            ];

        
    }
}