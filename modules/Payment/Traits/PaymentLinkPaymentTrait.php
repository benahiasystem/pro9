<?php

// ######## INICIO MIGRACIÓN MONEDA VENEZUELA ########

namespace Modules\Payment\Traits;

use App\Http\Controllers\Tenant\DocumentPaymentController;
use App\Http\Requests\Tenant\DocumentPaymentRequest;
use App\Models\Tenant\{
    Document,
    DocumentPayment,
    PaymentMethodType,
};
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Modules\Finance\Http\Controllers\IncomeController;
use Modules\Finance\Http\Requests\IncomeRequest;
use Modules\Finance\Models\{
    Income,
    IncomeReason,
    IncomeType,
};
use Modules\Payment\Models\{
    PaymentConfiguration,
    PaymentLink,
    PaymentLinkPayment,
};


/**
 * Registro de los pagos generados por un link de pago
 *
 * Al generar el link solo se guarda el detalle de lo que se va a cobrar (pendiente),
 * los pagos se registran recien cuando el link es marcado como pagado
 */
trait PaymentLinkPaymentTrait
{

    /**
     *
     * Tipos de comprobante que se pueden cobrar con un link de pago
     *
     * Para habilitar un nuevo tipo basta con agregarlo a esta lista
     *
     * @return array
     */
    public function getPaymentInstances()
    {
        return [
            'document' => [
                'model' => Document::class,
                'payment_model' => DocumentPayment::class,
                'foreign_key' => 'document_id',
                'controller' => DocumentPaymentController::class,
                'request' => DocumentPaymentRequest::class,
            ],
        ];
    }


    /**
     *
     * Obtener la configuración del tipo de comprobante
     *
     * @param  string $instance_type
     * @return array
     */
    public function getPaymentInstance($instance_type)
    {
        $instances = $this->getPaymentInstances();

        if(!isset($instances[$instance_type])) throw new Exception('El tipo de comprobante no permite registrar pagos desde un link de pago');

        return array_merge($instances[$instance_type], ['instance_type' => $instance_type]);
    }


    /**
     *
     * Obtener la configuración a partir del modelo del comprobante
     *
     * @param  string $record_type
     * @return array
     */
    public function getPaymentInstanceByRecordType($record_type)
    {
        foreach ($this->getPaymentInstances() as $instance_type => $instance) {
            if($instance['model'] === $record_type) return array_merge($instance, ['instance_type' => $instance_type]);
        }

        throw new Exception('El tipo de comprobante no permite registrar pagos desde un link de pago');
    }


    /**
     *
     * Obtener el comprobante a cobrar segun su tipo
     *
     * @param  string $instance_type
     * @param  int $record_id
     * @return Document
     */
    public function getPaymentInstanceRecord($instance_type, $record_id)
    {
        $model = $this->getPaymentInstance($instance_type)['model'];

        $record = $model::find($record_id);

        if(!$record) throw new Exception('No se encontró el comprobante asociado al link de pago');

        return $record;
    }


    /**
     *
     * Obtener el saldo pendiente del comprobante
     *
     * @param  Document $record
     * @return float
     */
    public function getPendingPayment($record)
    {
        return round((float) $record->total - (float) $record->payments()->sum('payment'), 2);
    }


    /**
     *
     * Registrar los pagos pendientes del link de pago
     *
     * Se ejecuta al marcar el link como pagado, es idempotente porque solo
     * procesa el detalle que aun no generó su pago
     *
     * @param  PaymentLink $payment_link
     * @return int cantidad de pagos registrados
     */
    public function registerPaymentLinkPayments(PaymentLink $payment_link)
    {

        $this->setActingUser($payment_link);

        return DB::connection('tenant')->transaction(function () use ($payment_link) {

            $rows = $payment_link->payments()->wherePending()->get();

            foreach ($rows as $row) {

                $payment = $row->record_type
                            ? $this->storeRecordPayment($row)
                            : $this->storeIncomePayment($payment_link, $row);

                $row->setAsPaid($payment);

            }

            return $rows->count();

        });

    }


    /**
     *
     * Definir el usuario que registra los pagos
     *
     * Cuando el link se confirma desde la url publica no hay sesión, y el registro
     * del pago necesita un usuario (destino del pago, caja, ingreso)
     *
     * @param  PaymentLink $payment_link
     * @return void
     */
    private function setActingUser(PaymentLink $payment_link)
    {

        if(auth()->check()) return;

        if(!$payment_link->user_id) throw new Exception('El link de pago no tiene un usuario asociado para registrar los pagos');

        auth()->onceUsingId($payment_link->user_id);

    }


    /**
     *
     * Registrar el pago del comprobante reutilizando el controlador segun su tipo
     *
     * @param  PaymentLinkPayment $row
     * @return DocumentPayment
     */
    private function storeRecordPayment($row)
    {

        $instance = $this->getPaymentInstanceByRecordType($row->record_type);
        $record = $row->record;

        if(!$record) throw new Exception('No se encontró el comprobante asociado al link de pago');

        $total = round((float) $row->total, 2);
        $pending = $this->getPendingPayment($record);

        if($total > $pending) throw new Exception("El saldo pendiente de {$record->number_full} ({$pending}) es menor al monto del link de pago ({$total})");

        $inputs = array_merge($this->getPaymentInputs($total), [
            $instance['foreign_key'] => $record->id,
        ]);

        $payment_request_class = $instance['request'];
        $payment_response = app($instance['controller'])->store($payment_request_class::create('', 'POST', $inputs));

        if(!($payment_response['success'] ?? false)) throw new Exception("Error al registrar el pago de {$record->number_full}");

        $payment_model = $instance['payment_model'];

        return $payment_model::findOrFail($payment_response['id']);

    }


    /**
     *
     * Registrar el ingreso (finanzas) cuando el cobro no está asociado a un comprobante
     *
     * @param  PaymentLink $payment_link
     * @param  PaymentLinkPayment $row
     * @return \Modules\Finance\Models\IncomePayment
     */
    private function storeIncomePayment($payment_link, $row)
    {

        $income_request = IncomeRequest::create('', 'POST', $this->getIncomeInputs($payment_link, $row->total));
        $income_response = app(IncomeController::class)->store($income_request);

        $income = Income::findOrFail($income_response['data']['id']);

        $payment = $income->payments->first();

        if(!$payment) throw new Exception('No se pudo registrar el pago del ingreso asociado al link de pago');

        return $payment;

    }


    /**
     *
     * Armar los datos necesarios para registrar el ingreso (finanzas) asociado al link de pago
     *
     * Incluye los campos que no valida IncomeRequest pero que son obligatorios
     * en base de datos y/o para la generación del pdf
     *
     * @param  PaymentLink $payment_link
     * @param  float $total
     * @return array
     */
    private function getIncomeInputs($payment_link, $total)
    {

        $income_type = IncomeType::where('id', 3)->first();

        if(!$income_type) throw new Exception('No se encontró un tipo de comprobante para registrar el ingreso');

        $income_reason = IncomeReason::first();

        if(!$income_reason) throw new Exception('No se encontró un motivo para registrar el ingreso');

        $establishment_id = optional($payment_link->user)->establishment_id ?? optional(auth()->user())->establishment_id;

        if(!$establishment_id) throw new Exception('No se encontró el establecimiento para registrar el ingreso');

        $total = round((float) $total, 2);
        $date_of_issue = Carbon::now()->format('Y-m-d');

        return [

            // cabecera
            'id' => null,
            'establishment_id' => $establishment_id,
            'income_type_id' => $income_type->id,
            'income_reason_id' => $income_reason->id,
            'customer' => optional($payment_link->person)->name ?? 'Clientes varios',
            'currency_type_id' => 'VES',
            'date_of_issue' => $date_of_issue,
            'time_of_issue' => Carbon::now()->format('H:i:s'),
            'exchange_rate_sale' => 1,
            'total' => $total,

            // detalle
            'items' => [
                [
                    'description' => 'Cobro por link de pago',
                    'total' => $total,
                ],
            ],

            // pagos
            'payments' => [
                $this->getPaymentInputs($total, $date_of_issue),
            ],

        ];

    }


    /**
     *
     * Datos base del pago generado a partir del link de pago
     *
     * @param  float $payment
     * @param  string|null $date_of_payment
     * @return array
     */
    private function getPaymentInputs($payment, $date_of_payment = null)
    {

        return [
            'date_of_payment' => $date_of_payment ?? Carbon::now()->format('Y-m-d'),
            'payment_method_type_id' => $this->getIncomePaymentMethodTypeId(),
            'payment_destination_id' => 'cash',
            'reference' => null,
            'has_card' => false,
            'card_brand_id' => null,
            'change' => null,
            'payment' => $payment,
        ];

    }


    /**
     *
     * Obtener el método de pago configurado para los links de pago
     *
     * @return string
     */
    private function getIncomePaymentMethodTypeId()
    {
        $payment_configuration = PaymentConfiguration::select('default_payment_for_payment_links')->first();

        return optional($payment_configuration)->default_payment_for_payment_links ?? PaymentMethodType::CASH_PAYMENT_ID;
    }

}

// ######## FIN MIGRACIÓN MONEDA VENEZUELA ########
