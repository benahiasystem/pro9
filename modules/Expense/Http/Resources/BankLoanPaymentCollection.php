<?php

namespace Modules\Expense\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;
use Modules\Expense\Models\BankLoan;
use Modules\Expense\Models\BankLoanPayment;

class BankLoanPaymentCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return mixed
     */
    public function toArray($request)
    {
        return $this->collection->transform(function(BankLoanPayment $row, $key) {
            return [
                'id' => $row->id,
                'date_of_payment' => $row->date_of_payment->format('d/m/Y'),
                'expense_method_type_description' => \App\Services\Payments\ReceiptPresentation::method($row),
                'destination_description' => \App\Services\Payments\ReceiptPresentation::destination($row),
                'reference' => $row->reference,
                // 'filename' => ($row->payment_file) ? $row->payment_file->filename:null,
                'payment' => $row->payment,
            ];
        });
    }
}
