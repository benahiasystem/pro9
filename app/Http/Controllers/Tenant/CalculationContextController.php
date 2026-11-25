<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Database\IntegerTransport;
use App\Models\Tenant\{Company, Document, Person, PaymentMethodType};
use App\Rules\PositiveBigint;
use App\Services\Fiscal\{FiscalProfileService, SpecialTaxpayerPolicy};
use Modules\Finance\Traits\FinanceTrait;

// ######## INICIO CONTEXTO DE CALCULO LOCAL ########
final class CalculationContextController extends Controller
{
    use FinanceTrait;

    public function __invoke(\Illuminate\Http\Request $request)
    {
        $input = $request->validate(['document_id'=>['sometimes', 'required', new PositiveBigint()],
            'customer_id'=>'nullable|integer', 'dates'=>'required|array|min:1|max:101',
            'dates.*'=>'required|date_format:Y-m-d', 'establishment_id'=>'nullable|integer']);
        $customerId = $input['customer_id'] ?? null;
        if (isset($input['document_id'])) {
            $document = Document::withoutEagerLoads()->findOrFail(IntegerTransport::positiveKey($input['document_id']));
            DocumentFiscalController::authorizeDocument($document);
            abort_unless(auth()->user()->type === 'admin' || auth()->user()->create_payment, 403);
            $customerId = $document->customer_id;
        }
        $company = Company::firstOrFail();
        $customer = $customerId ? Person::whereType('customers')->findOrFail($customerId) : null;
        $dates = [];
        foreach (array_unique($input['dates']) as $date) {
            $issuer = FiscalProfileService::at($company, $date);
            $buyer = $customer ? FiscalProfileService::at($customer, $date) : [];
            $dates[$date] = ['issuer_spe'=>SpecialTaxpayerPolicy::spe($issuer, $date),
                'buyer_spe'=>SpecialTaxpayerPolicy::spe($buyer, $date), 'issuer'=>$issuer, 'buyer'=>$buyer,
                'retention'=>SpecialTaxpayerPolicy::retention($issuer, $buyer, $date)];
        }
        $data = ['version'=>1, 'enabled'=>(bool)$company->igtf_enabled, 'rate'=>$company->igtf_rate,
            'dates'=>$dates, 'methods'=>PaymentMethodType::getPaymentMethodTypes(), 'destinations'=>$this->getPaymentDestinations()];
        $data['revision'] = hash('sha256', json_encode($data));
        return ['data'=>$data];
    }
}
// ######## FIN CONTEXTO DE CALCULO LOCAL ########
