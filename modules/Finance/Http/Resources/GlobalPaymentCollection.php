<?php

namespace Modules\Finance\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class GlobalPaymentCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return $this->collection->filter(fn($row)=>\App\Services\Payments\Movement::row($row) !== null)->values()->map(function ($row, $key) {

            $data_person = $row->data_person;

            $document_type = '';
            $payment = ($row->payment) ? $row->payment : null;
            if ($payment !== null && $row->payment->associated_record_payment !== null && $row->payment->associated_record_payment->document_type) {

                $document_type = $row->payment->associated_record_payment->document_type->description;

            } elseif ($row->instance_type == 'technical_service') {

                $document_type = 'ST';

            } elseif ($payment !== null && isset($row->payment->associated_record_payment->prefix)) {

                $document_type = $row->payment->associated_record_payment->prefix;

            }

            $cci = $row->getCciBankAcount();
            // $cci = $row->getCciAcoount();
            $personName = $data_person->name;
            if (!is_string($personName)) {
                if (property_exists($personName, 'description')) {
                    // Los bancos con transacciones
                    $personName = $personName->description;
                }
            }

            $movement=\App\Services\Payments\Movement::row($row);
            return [
                'report_version' => 2,
                'id' => $row->id,
                'destination_description' => $row->getDestinationDescriptionPayment(),
                // 'destination_description' => $row->destination_description,
                'cci' => $cci,
                'date_of_payment' => ($payment !== null && $row->payment->date_of_payment !== null) ? $row->payment->date_of_payment->format('d-m-Y') : '',
                'payment_method_type_description' => $this->getPaymentMethodTypeDescription($row),
                'reference' => ($payment !== null) ? $row->payment->reference : '',
                'total' => $movement['amount'],
                'number_full' => ($payment !== null && $row->payment->associated_record_payment) ? $row->payment->associated_record_payment->number_full : '',
                'currency_type_id' => $movement['currency_type_id'],
                // 'document_type_description' => ($row->payment->associated_record_payment->document_type) ? $row->payment->associated_record_payment->document_type->description:'NV',
                'document_type_description' => $document_type,
                'person_name' => $personName,
                'person_number' => format_person_identity_document($data_person),
                // 'payment' => $row->payment,
                // 'payment_type' => $row->payment_type,
                'instance_type' => $row->instance_type,
                'instance_type_description' => $row->instance_type_description,
                'user_id' => $row->user_id,
                'user_name' => optional($row->user)->name,
                'file_url' => optional($row->payment)->getPaymentFileUrl()
            ];
        });
    }

    // ######## INICIO CLASIFICACION DE COBROS Y CAJA ########
    public function with($request)
    {
        return ['totals_by_version'=>\App\Services\Payments\FinancialReport::totals($this->collection)];
    }
    // ######## FIN CLASIFICACION DE COBROS Y CAJA ########


    public function getPaymentMethodTypeDescription($row)
    {
        return $row->report_context['method']['description'];
    }


}
