<?php
namespace Modules\Sale\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Cash;
use App\Models\Tenant\CashDocument;
use Modules\Sale\Http\Requests\TechnicalServicePaymentRequest;
use Modules\Sale\Http\Resources\TechnicalServicePaymentCollection;
use App\Models\Tenant\PaymentMethodType;
use Modules\Sale\Models\TechnicalService;
use Modules\Finance\Traits\FinanceTrait;
use Illuminate\Support\Facades\DB;
use Modules\Sale\Models\TechnicalServicePayment;

class TechnicalServicePaymentController extends Controller
{

    use FinanceTrait;

    public function records($technical_service_id)
    {
        $records = TechnicalServicePayment::where('technical_service_id', $technical_service_id)->get();

        return new TechnicalServicePaymentCollection($records);
    }

    public function tables()
    {
        return [
            'payment_method_types' => PaymentMethodType::getActiveReceiptMethods(),
            'payment_destinations' => $this->getPaymentDestinations()
        ];
    }


    public function document($technical_service_id)
    {
        // ######## INICIO MONEDA DEL MEDIO DE COBRO ########
        return \App\Support\Exact\ReadSnapshot::run(DB::connection('tenant'), function () use ($technical_service_id) {
        $record = TechnicalService::withoutEagerLoads()->findOrFail($technical_service_id);
        $totals = \App\Services\Payments\AppliedReceiptBalance::transport(\App\Services\Payments\AppliedReceiptBalance::values($record, true));
        return [
            'number_full' => $record->number_full,
            // ######## INICIO CLASIFICACION DE COBROS Y CAJA ########
            'currency_type_id' => $record->currency_type_id,
            'exchange_rate_sale' => $record->exchange_rate_sale,
            // ######## FIN CLASIFICACION DE COBROS Y CAJA ########
        ] + $totals;
        });
        // ######## FIN MONEDA DEL MEDIO DE COBRO ########
    }


    public function store(TechnicalServicePaymentRequest $request)
    {
        $id = $request->input('id');

        DB::connection('tenant')->transaction(function () use ($id, $request) {

            $record = TechnicalServicePayment::query()->firstOrNew(['id' => $id]);
            // ######## INICIO MONEDA DEL MEDIO DE COBRO ########
            $input = $request->only(['technical_service_id', 'date_of_payment', 'payment_method_type_id', 'reference', 'change', 'payment']);
            if (!$record->exists) {
                $service = TechnicalService::withoutEagerLoads()->lockForUpdate()->findOrFail($input['technical_service_id'] ?? null);
                $values = \App\Services\Payments\ReceiptAmounts::forMethod($input['payment_method_type_id'] ?? null, $service->currency_type_id, $service->exchange_rate_sale, $request->all());
                \App\Services\Payments\AppliedReceiptBalance::assertFits(\App\Services\Payments\AppliedReceiptBalance::values($service, true, true), $values);
                $record->fill(array_replace($input, $values));
                $record->calculation_snapshot = $values['calculation_snapshot'];
            } else {
                \App\Services\Payments\ExistingReceipts::assertCurrencyFields($record, $request->all());
                $record->fill($input);
            }
            // ######## FIN MONEDA DEL MEDIO DE COBRO ########
            $record->save();
            $this->createGlobalPayment($record, $request->all());
        });

        return [
            'success' => true,
            'message' => ($id)?'Pago editado con éxito':'Pago registrado con éxito'
        ];
    }


    public function destroy($id)
    {
        DB::connection('tenant')->transaction(function () use ($id) {
            $serviceId = TechnicalServicePayment::withoutEagerLoads()->findOrFail($id)->technical_service_id;
            TechnicalService::withoutEagerLoads()->lockForUpdate()->findOrFail($serviceId);
            TechnicalServicePayment::withoutEagerLoads()->lockForUpdate()->findOrFail($id)->delete();
        });

        return [
            'success' => true,
            'message' => 'Pago eliminado con éxito'
        ];
    }



}
