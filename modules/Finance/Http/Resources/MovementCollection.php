<?php

// ######## INICIO MIGRACIÓN MONEDA VENEZUELA ########

namespace Modules\Finance\Http\Resources;

use App\Models\Tenant\TransferAccountPayment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Modules\Finance\Http\Controllers\MovementController;
use Modules\Finance\Models\GlobalPayment;


class MovementCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @param Request $request
     *
     * @return array
     */
    // ######## INICIO MONEDA DEL MEDIO DE COBRO ########
    public function toArray($request)
    {
        $balances = new \App\Services\Payments\MovementBalances();
        $page = max(1, (int) ($request->page ?? 1));
        if ($page > 1) {
            $pageSize = $request->has('paginate') ? 1000 : (int) config('tenant.items_per_page');
            $previous = app(MovementController::class)->getRecords($request, GlobalPayment::class)
                ->limit(($page - 1) * $pageSize)->get();
            \App\Services\Payments\Movement::preload($previous);
            foreach ($previous as $global) {
                if (($movement = \App\Services\Payments\Movement::row($global)) && in_array($movement['currency_type_id'], ['VES', 'USD'], true)) $balances->apply($movement);
            }
        }
        \App\Services\Payments\Movement::preload($this->collection);
        return $this->collection->map(function (GlobalPayment $row, $key) use ($balances) {
            $movement = \App\Services\Payments\Movement::row($row);
            $payment = $row->payment;
            $legacy = !$movement && ($row->report_context['version'] ?? null) !== 2
                && !isset($row->report_context['reversal']) && $payment && !$payment->reversed_at
                && !($payment instanceof \App\Models\Tenant\DocumentPayment && $payment->receipt_parent_id);
            if (!$movement && !$legacy) return null;
            $isTransfer = $payment instanceof TransferAccountPayment;
            $dataPerson = $row->data_person;
            $parent = $payment->associated_record_payment;
            $documentType = $parent->document_type->description ?? ($parent->prefix ?? '');
            $destination = $row->getDestinationWithCci();
            $type = $legacy ? $row->type_movement : ($movement['direction'] === 'income' ? 'input' : 'output');
            $amount = $legacy ? '-' : \App\Support\Exact\Decimal::format($movement['calculation_snapshot']['values']['amount'], 2, '.', '');
            $balance = !$legacy && in_array($movement['currency_type_id'], ['VES', 'USD'], true)
                ? \App\Support\Exact\Decimal::format($balances->applyExact($movement), 2, '.', '') : '-';
            $date = $isTransfer ? $payment->date_of_movement : $payment->date_of_payment;
            $personName = $isTransfer ? ($destination['name'] ?: '-') : $dataPerson->name;
            if ($row->instance_type === 'bank_loan_payment') $personName = $personName->description ?? '';
            return [
                'index' => $key + 1,
                'id' => $row->id,
                'payments' => $movement['amount'] ?? null,
                'calculation_snapshot' => $movement['calculation_snapshot'] ?? null,
                'document_type' => $documentType,
                'document_type_description' => $documentType,
                'destination_description' => $movement['destination_label'] ?? $row->destination_description,
                'destination_array' => $destination,
                'destination_name' => $movement['destination_label'] ?? $row->destination_description,
                'date_of_payment_class' => get_class($payment),
                'date_of_payment' => $date ? $date->format('d-m-Y h:i A') : ($movement['date'] ?? ''),
                'payment_method_type_description' => $movement['method']['description'] ?? '',
                'reference' => $payment->reference,
                'total' => $movement['amount'] ?? null,
                'number_full' => $movement['number'] ?? ($parent->number_full ?? ''),
                'currency_type_id' => $movement['currency_type_id'] ?? null,
                'person_name' => $personName,
                'person_number' => $isTransfer ? ($destination['cci'] ?? '') : $dataPerson->number,
                'instance_type' => $row->instance_type,
                'instance_type_description' => $isTransfer ? 'Transferencia Bancaria' : $row->instance_type_description,
                'user_id' => $row->user_id,
                'user_name' => optional($row->user)->name,
                'type_movement' => $type,
                'input' => $type === 'input' ? $amount : '-',
                'output' => $type === 'output' ? $amount : '-',
                'balance' => $balance,
                'items' => $this->getItems($row),
            ];
        })->filter()->values();
    }
    // ######## FIN MONEDA DEL MEDIO DE COBRO ########

    public function getItems(GlobalPayment $row)
    {
        $instanceType = $row->instance_type;
        if (in_array($instanceType, ['expense', 'income', 'bank_loan_payment'])) {

            // ######## INICIO CLASIFICACION DE COBROS Y CAJA ########
            // No modificar la relación cargada: varios movimientos pueden compartirla.
            return $row->payment->associated_record_payment->items->map(function ($row) {
                return [
                    'description' => data_get($row, 'description'),
                ];
            });
            // ######## FIN CLASIFICACION DE COBROS Y CAJA ########
        }

        return [];

    }


}

// ######## FIN MIGRACIÓN MONEDA VENEZUELA ########
