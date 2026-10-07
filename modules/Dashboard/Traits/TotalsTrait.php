<?php
// ######## INICIO TASAS OCHO DECIMALES: CONVERSIONES EXACTAS ########
// ######## FIN TASAS OCHO DECIMALES: CONVERSIONES EXACTAS ########
// ######## INICIO MIGRACIÓN MONEDA VENEZUELA ########

namespace Modules\Dashboard\Traits;

use App\Models\Tenant\Document;
use App\Models\Tenant\DocumentPayment;
use App\Models\Tenant\SaleNote;
use App\Models\Tenant\SaleNotePayment;
use Carbon\Carbon;
use App\Models\Tenant\Person;
use App\Models\Tenant\Purchase;
use Modules\Expense\Models\Expense;
use Modules\Order\Models\OrderNote;


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

        if($date_start && $date_end){

            $purchases = $this->filterEstablishment(Purchase::query()->whereIn('state_type_id', ['01','03','05','07','13']), $establishment_id)
                                        ->whereBetween('date_of_issue', [$date_start, $date_end])
                                        ->get();

        }else{

            $purchases = $this->filterEstablishment(Purchase::query()->whereIn('state_type_id', ['01','03','05','07','13']), $establishment_id)
                                        ->get();
        }


        $purchases_total = $purchases->where('currency_type_id', 'VES')->sum('total') + $purchases->where('currency_type_id', 'VES')->sum('total_perception');
        $purchases_total_usd = 0;


        $purchase_total_payment = 0;
        $purchase_total_payment_usd = 0;


        foreach ($purchases as $purchase)
        {

            if($purchase->currency_type_id == 'VES'){

                $purchase_total_payment += collect($purchase->purchase_payments)->sum('payment');

            }else{
                $purchases_total_usd = (string) \App\Services\ExchangeRates\ExchangeRateMath::rational($purchases_total_usd)->plus(\App\Services\ExchangeRates\ExchangeRateMath::rational($purchase->total)->multipliedBy(\App\Services\ExchangeRates\ExchangeRateMath::rational($purchase->exchange_rate_sale))->plus(\App\Services\ExchangeRates\ExchangeRateMath::rational($purchases->sum('total_perception'))))->toBigDecimal();
                $purchase_total_payment_usd = (string) \App\Services\ExchangeRates\ExchangeRateMath::rational($purchase_total_payment_usd)->plus(\App\Services\ExchangeRates\ExchangeRateMath::rational(collect($purchase->purchase_payments)->sum('payment'))->multipliedBy(\App\Services\ExchangeRates\ExchangeRateMath::rational($purchase->exchange_rate_sale)))->toBigDecimal();
            }
        }

        $total = $purchases_total + $purchases_total_usd;
        $total_payment = $purchase_total_payment +$purchase_total_payment_usd;

        return [
            'totals' => [
                'total_payment' => round($total_payment,2),
                'total' => round($total,2),
            ]
        ];
    }


    public function get_expense_totals($establishment_id, $date_start, $date_end)
    {


        if($date_start && $date_end){

            $expenses = $this->filterEstablishment(Expense::query(), $establishment_id)
                                        ->whereBetween('date_of_issue', [$date_start, $date_end])
                                        ->where('state_type_id','05')
                                        ->get();

        }else{

            $expenses = $this->filterEstablishment(Expense::query(), $establishment_id)
                                        ->where('state_type_id','05')
                                        ->get();
        }

        $expenses_total = $expenses->where('currency_type_id', 'VES')->sum('total');

        $expense_dolla = $expenses->where('currency_type_id', 'USD');

        foreach ($expense_dolla as $exp) {
            $expenses_total = (string) \App\Services\ExchangeRates\ExchangeRateMath::rational($expenses_total)->plus(\App\Services\ExchangeRates\ExchangeRateMath::rational($exp->total)->multipliedBy(\App\Services\ExchangeRates\ExchangeRateMath::rational($exp->exchange_rate_sale)))->toBigDecimal();
        }

        $expense_total_payment = 0;

        foreach ($expenses as $expense)
        {
            $expense_total_payment += collect($expense->payments)->sum('payment');
        }

        return [
            'totals' => [
                'total_payment' => round($expense_total_payment,2),
                'total' => round((float) \App\Services\ExchangeRates\ExchangeRateMath::finalAmount($expenses_total, 2),2),
            ]
        ];

    }


    public function get_sale_note_totals($establishment_id, $date_start, $date_end)
    {

        if($date_start && $date_end){
            $sale_notes = $this->filterEstablishment(SaleNote::query()->whereStateTypeAccepted(), $establishment_id)
                                           ->where('changed', false)
                                           ->whereBetween('date_of_issue', [$date_start, $date_end])->get();
        }else{
            $sale_notes = $this->filterEstablishment(SaleNote::query()->whereStateTypeAccepted(), $establishment_id)
                                           ->where('changed', false)->get();
        }


        //VES
        $sale_note_total_pen = 0;
        $sale_note_total_payment_pen = 0;

        $sale_note_total_pen = collect($sale_notes->where('currency_type_id', 'VES'))->sum('total');

        //USD
        $sale_note_total_usd = 0;
        $sale_note_total_payment_usd = 0;

        //TWO CURRENCY
        foreach ($sale_notes as $sale_note)
        {

            if($sale_note->currency_type_id == 'VES'){

                $sale_note_total_payment_pen += collect($sale_note->payments)->sum('payment');

            }else{

                $sale_note_total_usd = (string) \App\Services\ExchangeRates\ExchangeRateMath::rational($sale_note_total_usd)->plus(\App\Services\ExchangeRates\ExchangeRateMath::rational($sale_note->total)->multipliedBy(\App\Services\ExchangeRates\ExchangeRateMath::rational($sale_note->exchange_rate_sale)))->toBigDecimal();
                $sale_note_total_payment_usd = (string) \App\Services\ExchangeRates\ExchangeRateMath::rational($sale_note_total_payment_usd)->plus(\App\Services\ExchangeRates\ExchangeRateMath::rational(collect($sale_note->payments)->sum('payment'))->multipliedBy(\App\Services\ExchangeRates\ExchangeRateMath::rational($sale_note->exchange_rate_sale)))->toBigDecimal();

            }
        }

        //TOTALS
        $sale_note_total = $sale_note_total_pen + $sale_note_total_usd;
        $sale_note_total_payment = $sale_note_total_payment_pen + $sale_note_total_payment_usd;


        return [
            'totals' => [
                'total_payment' => round($sale_note_total_payment,2),
                'total' => round($sale_note_total,2),
            ]
        ];
    }


    public function get_document_totals($establishment_id, $date_start, $date_end)
    {

        if($date_start && $date_end){
            $documents = $this->filterEstablishment(Document::query(), $establishment_id)->whereBetween('date_of_issue', [$date_start, $date_end])->get();
        }else{
            $documents = $this->filterEstablishment(Document::query(), $establishment_id)->get();
        }

        //VES
        $document_total_pen = 0;
        $document_total_payment_pen = 0;
        $document_total_note_credit_pen = 0;

        $document_total_pen = collect($documents->whereIn('state_type_id', ['01','03','05','07','13'])->whereIn('document_type_id', ['01','08']))->where('currency_type_id', 'VES')->sum('total');

        //USD
        $document_total_usd = 0;
        $document_total_note_credit_usd = 0;
        $document_total_payment_usd = 0;

        $documents_usd = $documents->whereIn('state_type_id', ['01','03','05','07','13'])
                                    ->whereIn('document_type_id', ['01','08'])
                                    ->where('currency_type_id', 'USD');

        foreach ($documents_usd as $dusd) {
            $document_total_usd = (string) \App\Services\ExchangeRates\ExchangeRateMath::rational($document_total_usd)->plus(\App\Services\ExchangeRates\ExchangeRateMath::rational($dusd->total)->multipliedBy(\App\Services\ExchangeRates\ExchangeRateMath::rational($dusd->exchange_rate_sale)))->toBigDecimal();
        }

        //TWO CURRENCY

        foreach ($documents as $document)
        {
            if($document->currency_type_id == 'VES'){

                if(in_array($document->state_type_id,['01','03','05','07','13'])){

                    $document_total_payment_pen += collect($document->payments)->sum('payment');
                    $document_total_note_credit_pen += ($document->document_type_id == '07') ? $document->total:0; //nota de credito

                }


            }else{

                if(in_array($document->state_type_id,['01','03','05','07','13'])){

                    $document_total_payment_usd = (string) \App\Services\ExchangeRates\ExchangeRateMath::rational($document_total_payment_usd)->plus(\App\Services\ExchangeRates\ExchangeRateMath::rational(collect($document->payments)->sum('payment'))->multipliedBy(\App\Services\ExchangeRates\ExchangeRateMath::rational($document->exchange_rate_sale)))->toBigDecimal();
                    $document_total_note_credit_usd = (string) \App\Services\ExchangeRates\ExchangeRateMath::rational($document_total_note_credit_usd)->plus(\App\Services\ExchangeRates\ExchangeRateMath::rational(($document->document_type_id == '07') ? (\App\Services\ExchangeRates\ExchangeRateMath::rational($document->total)->multipliedBy(\App\Services\ExchangeRates\ExchangeRateMath::rational($document->exchange_rate_sale)))->toBigDecimal():0))->toBigDecimal(); //nota de credito

                }

            }

        }

        //TOTALS
        $document_total = $document_total_pen + $document_total_usd;
        $document_total_note_credit = $document_total_note_credit_pen + $document_total_note_credit_usd;
        $document_total_payment = $document_total_payment_pen + $document_total_payment_usd;

        $document_total = round(($document_total - $document_total_note_credit),2);

        return [
            'totals' => [
                'total_payment' => round($document_total_payment,2),
                'total' => round($document_total,2),
            ]
        ];
    }


    /**
     *
     * Obtener suma total de pedidos
     *
     * @param  int $establishment_id
     * @param  string $date_start
     * @param  string $date_end
     * @return float
     */
    public function getTotalsOrderNote($establishment_id, $date_start, $date_end)
    {
        $order_notes = OrderNote::filterTotalsReport($establishment_id, $date_start, $date_end);

        return $order_notes->get()->sum(function($row){
            return $row->getTransformTotal();
        });
    }


    /**
     * Redondear número
     *
     * @param  float $value
     * @param  int $decimals
     * @return float
     */
    public function roundNumber($value, $decimals = 2)
    {
        return number_format($value, $decimals, ".", "");
    }

}

// ######## FIN MIGRACIÓN MONEDA VENEZUELA ########
