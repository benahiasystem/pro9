<?php
namespace Modules\Sale\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Sale\Http\Requests\QuotationPaymentRequest;
use Modules\Sale\Http\Resources\QuotationPaymentCollection;
use App\Models\Tenant\PaymentMethodType;
use App\Models\Tenant\Quotation;
use Modules\Finance\Traits\FinanceTrait;
use Modules\Finance\Traits\FilePaymentTrait;
use Illuminate\Support\Facades\DB;
use Modules\Sale\Models\QuotationPayment;

class QuotationPaymentController extends Controller
{

    use FinanceTrait, FilePaymentTrait;

    public function records($quotation_id)
    {
        $records = QuotationPayment::where('quotation_id', $quotation_id)->get();

        return new QuotationPaymentCollection($records);
    }

    public function tables()
    {
        return [
            'payment_method_types' => PaymentMethodType::getActiveReceiptMethods(),
            // 'payment_method_types' => PaymentMethodType::all(),
            'payment_destinations' => $this->getPaymentDestinations()

        ];
    }

    public function document($quotation_id)
    {
        // ######## INICIO MONEDA DEL MEDIO DE COBRO ########
        return \App\Support\Exact\ReadSnapshot::run(DB::connection('tenant'), function () use ($quotation_id) {
        $record = Quotation::withoutEagerLoads()->findOrFail($quotation_id);
        $totals = \App\Services\Payments\AppliedReceiptBalance::transport(\App\Services\Payments\AppliedReceiptBalance::values($record));
        return [
            'number_full' => $record->identifier,
            // ######## INICIO CLASIFICACION DE COBROS Y CAJA ########
            'currency_type_id' => $record->currency_type_id,
            'exchange_rate_sale' => $record->exchange_rate_sale,
            // ######## FIN CLASIFICACION DE COBROS Y CAJA ########
        ] + $totals;
        });
        // ######## FIN MONEDA DEL MEDIO DE COBRO ########
    }

    public function store(QuotationPaymentRequest $request)
    {
        $id = $request->input('id');

        DB::connection('tenant')->transaction(function () use ($id, $request) {

            $record = QuotationPayment::firstOrNew(['id' => $id]);
            // ######## INICIO MONEDA DEL MEDIO DE COBRO ########
            $input = $request->only(['quotation_id', 'date_of_payment', 'payment_method_type_id', 'reference', 'change', 'payment']);
            if (!$record->exists) {
                $quotation = Quotation::withoutEagerLoads()->lockForUpdate()->findOrFail($input['quotation_id'] ?? null);
                $values = \App\Services\Payments\ReceiptAmounts::forMethod($input['payment_method_type_id'] ?? null, $quotation->currency_type_id, $quotation->exchange_rate_sale, $request->all());
                \App\Services\Payments\AppliedReceiptBalance::assertFits(\App\Services\Payments\AppliedReceiptBalance::values($quotation, false, true), $values);
                $record->fill(array_replace($input, $values));
                $record->calculation_snapshot = $values['calculation_snapshot'];
            } else {
                \App\Services\Payments\ExistingReceipts::assertCurrencyFields($record, $request->all());
                $record->fill($input);
            }
            // ######## FIN MONEDA DEL MEDIO DE COBRO ########
            $record->save();
            $this->createGlobalPayment($record, $request->all());
            $this->saveFiles($record, $request, 'quotations');

        });

        return [
            'success' => true,
            'message' => ($id)?'Pago editado con éxito':'Pago registrado con éxito'
        ];
    }

    public function destroy($id)
    {
        DB::connection('tenant')->transaction(function () use ($id) {
            $quotationId = QuotationPayment::withoutEagerLoads()->findOrFail($id)->quotation_id;
            Quotation::withoutEagerLoads()->lockForUpdate()->findOrFail($quotationId);
            QuotationPayment::withoutEagerLoads()->lockForUpdate()->findOrFail($id)->delete();
        });

        return [
            'success' => true,
            'message' => 'Pago eliminado con éxito'
        ];
    }



}
