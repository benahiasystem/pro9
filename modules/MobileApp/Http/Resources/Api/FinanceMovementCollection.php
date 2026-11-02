<?php

namespace Modules\MobileApp\Http\Resources\Api;

use App\Models\Tenant\Cash;
use App\Models\Tenant\TransferAccountPayment;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * Fila unificada de movimientos para la app movil. Misma informacion que
 * MovementCollection (web) pero sin el saldo acumulado (incompatible con
 * cursor pagination): los totales van en finances/movements-summary.
 */
class FinanceMovementCollection extends ResourceCollection
{
    public function toArray($request)
    {
        return $this->collection->map(function ($row) {

            $payment = $row->payment;
            if (!$payment) return null;

            // ######## INICIO MONEDA DEL MEDIO DE COBRO ########
            $movement = \App\Services\Payments\Movement::row($row);
            $legacy = !$movement && ($row->report_context['version'] ?? null) !== 2
                && !isset($row->report_context['reversal']) && !$payment->reversed_at
                && !($payment instanceof \App\Models\Tenant\DocumentPayment && $payment->receipt_parent_id);
            if (!$movement && !$legacy) return null;
            $is_transfer = $row->payment_type === TransferAccountPayment::class;
            $document = $payment->associated_record_payment ?? null;
            $receiptCurrency = $movement['currency_type_id'] ?? null;
            $amount_decimal = $movement['amount'] ?? null;
            $amount_pen_exact = null;
            $amount_pen_decimal = null;
            if ($movement && $receiptCurrency === 'VES') {
                $amount_pen_exact = \App\Support\Exact\Decimal::of($movement['calculation_snapshot']['values']['amount']);
            } elseif ($movement && $receiptCurrency === 'USD' && !$is_transfer
                && ($document->currency_type_id ?? null) === 'VES' && $payment->getRawOriginal('exchange_rate') !== null) {
                $rate = \App\Support\Exact\Decimal::of($payment->getRawOriginal('exchange_rate'));
                if ($rate->isPositive()) {
                    $amount_pen_exact = \App\Support\Exact\Decimal::convert(
                        $movement['calculation_snapshot']['values']['amount'], 'USD', 'VES', $payment->getRawOriginal('exchange_rate'));
                }
            }
            if ($amount_pen_exact !== null) $amount_pen_decimal = \App\Support\Exact\Decimal::represent($amount_pen_exact);
            $type_movement = $movement ? ($movement['direction'] === 'income' ? 'input' : 'output')
                : ($is_transfer ? null : $row->type_movement);
            // ######## FIN MONEDA DEL MEDIO DE COBRO ########

            // Fecha y hora del movimiento
            $date = null;
            $time = null;
            if ($is_transfer && $payment->date_of_movement) {
                $date = $payment->date_of_movement->format('Y-m-d');
                $time = $payment->date_of_movement->format('H:i');
            } elseif ($document && $document->date_of_issue) {
                $date = $document->date_of_issue->format('Y-m-d');
                $time = $document->time_of_issue ? substr($document->time_of_issue, 0, 5) : null;
            } elseif ($payment->date_of_payment) {
                $date = $payment->date_of_payment->format('Y-m-d');
            }

            $data_person = $row->data_person;
            $person_name = $data_person->name ?? null;
            $person_number = $data_person->number ?? null;
            $number_full = $document->number_full ?? null;
            $instance_type = $row->instance_type;
            $instance_type_description = $row->instance_type_description;

            if ($instance_type === 'bank_loan_payment') {
                $person_name = optional($person_name)->description;
                $person_number = '';
                $number_full = $document ? $document->getNumberFull() : null;
            }

            if ($is_transfer) {
                $destination_data = $row->getDestinationWithCci();
                $person_name = $destination_data['name'] ?? '-';
                $person_number = $destination_data['cci'] ?? '';
                $instance_type_description = 'Transferencia bancaria';
            }

            // Metodo (ingresos usan payment_method_type; gastos expense_method_type)
            $method = $payment->payment_method_type ?? $payment->expense_method_type ?? null;

            // Documento origen: tipo legible
            $document_type_description = '';
            if ($document) {
                if ($document->document_type ?? null) {
                    $document_type_description = $document->document_type->description;
                } elseif (isset($document->prefix)) {
                    $document_type_description = $document->prefix;
                }
            }

            // Detalle (solo gastos, ingresos y prestamos, como la web)
            $items = [];
            if (in_array($instance_type, ['expense', 'income', 'bank_loan_payment']) && $document) {
                // ######## INICIO CLASIFICACION DE COBROS Y CAJA ########
                $items = collect($document->items ?? [])
                    ->map(fn($item) => ['description' => data_get($item, 'description')])
                    ->values();
                // ######## FIN CLASIFICACION DE COBROS Y CAJA ########
            }

            // Registro origen accionable desde la app (PDF + anular): solo income/expense
            $record = null;
            if (in_array($instance_type, ['income', 'expense']) && $document) {
                $record = [
                    'type' => $instance_type,
                    'id' => $document->id,
                    'external_id' => $document->external_id,
                    'state_type_id' => (string) $document->state_type_id,
                    // ruta publica print/* (DownloadController@toPrint); solo existe formato a4
                    'print_url' => url("print/{$instance_type}/{$document->external_id}"),
                ];
            }

            return [
                'id' => $row->id,
                'date_of_payment' => $date,
                'time_of_payment' => $time,
                'type_movement' => $type_movement,
                'instance_type' => $instance_type,
                'instance_type_description' => $instance_type_description,
                'document_type_description' => $document_type_description,
                'number_full' => $number_full,
                'person_name' => $person_name ?: null,
                'person_number' => $person_number ?: null,
                'payment_method_type_description' => $movement['method']['description'] ?? optional($method)->description,
                'destination' => [
                    'type' => $row->destination_type === Cash::class ? 'cash' : 'bank',
                    'id' => $row->destination_id,
                    'name' => $row->destination_description,
                ],
                'currency_type_id' => $receiptCurrency,
                'amount' => $amount_decimal === null ? null : (float) \App\Support\Exact\Decimal::represent($movement['calculation_snapshot']['values']['amount'], 2, true),
                'amount_decimal' => $amount_decimal,
                'amount_pen' => $amount_pen_exact === null ? null : (float) \App\Support\Exact\Decimal::represent($amount_pen_exact, 2, true),
                'amount_pen_decimal' => $amount_pen_decimal,
                'calculation_snapshot' => $movement['calculation_snapshot'] ?? null,
                'reference' => $payment->reference ?? null,
                'items' => $items,
                'record' => $record,
                'user_name' => optional($row->user)->name,
            ];
        })->filter()->values();
    }
}
