<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\{Company, Document, PaymentMethodType};
use App\Services\Fiscal\{FiscalAmounts, FiscalDocumentPersistence, FiscalNote, FiscalProfileService, PaymentQuote};
use App\Services\Payments\MethodCurrency;
use App\Support\Exact\Decimal as D;
use Illuminate\Http\Request;

final class DocumentPaymentQuoteController extends Controller
{
    public function __invoke(Request $request)
    {
        $data = $request->validate([
            'document_id'=>'nullable|integer', 'customer_id'=>'nullable|integer',
            'currency_type_id'=>'required_without:document_id|in:VES,USD',
            'exchange_rate_sale'=>'required_without:document_id|numeric|gt:0',
            'principal_due'=>'required_without:document_id|numeric|min:0',
            'payments'=>'required|array|max:100', 'payments.*'=>'array',
            'payments.*.date_of_payment'=>'required|date_format:Y-m-d',
            'payments.*.payment_method_type_id'=>'required|string',
            'payments.*.payment'=>'required|numeric|gt:0',
            'payments.*.currency_type_id'=>'nullable|in:VES,USD',
            'payments.*.original_amount'=>'nullable|numeric|gt:0',
            'payments.*.tendered_amount'=>'sometimes|required|numeric|gt:0',
            'payments.*.exchange_rate'=>'nullable|numeric|gt:0',
            'payments.*.id'=>'nullable|integer', 'payments.*.document_id'=>'nullable|integer',
            'payments.*.sale_note_id'=>'nullable|integer', 'payments.*.quotation_id'=>'nullable|integer',
            'payments.*.technical_service_id'=>'nullable|integer', 'payments.*.receipt_parent_id'=>'nullable|integer',
            'payments.*.source_sale_note_payment_id'=>'nullable|integer', 'payments.*.source_quotation_payment_id'=>'nullable|integer',
            'payments.*.source_technical_service_payment_id'=>'nullable|integer', 'payments.*._reused_receipt'=>'nullable|boolean',
            'payments.*.receipt_origin'=>'nullable|array', 'payments.*.payment_method_snapshot'=>'nullable|array',
            'payments.*.financial_intermediation'=>'nullable|in:none,financial_institution',
            // ######## INICIO RETIRADA DE EXCEPCION ADMINISTRATIVA IGTF ########
            'payments.*.igtf_status'=>'nullable|in:subject,not_applicable',
            'payments.*.exemption_reason'=>'prohibited',
            'payments.*.exception_basis'=>'prohibited',
            // ######## FIN RETIRADA DE EXCEPCION ADMINISTRATIVA IGTF ########
        ]);
        $document = !empty($data['document_id']) ? Document::findOrFail($data['document_id']) : null;
        if ($document) {
            DocumentFiscalController::authorizeDocument($document);
            abort_unless(auth()->user()->type === 'admin' || auth()->user()->create_payment, 403);
            if ($document->isVoidedOrRejected() || ($document->document_type_id !== '01' && !($document->document_type_id === '08' && FiscalNote::ordinary($document)))) {
                FiscalAmounts::error('document', 'Sólo se cobran facturas vigentes.');
            }
        }
        $company = Company::firstOrFail();
        $currency = $document ? $document->currency_type_id : $data['currency_type_id'];
        $rate = $document ? $document->exchange_rate_sale : $data['exchange_rate_sale'];
        $payable = $document ? $document->exactBalance(true) : $data['principal_due'];
        foreach ($data['payments'] as $row) {
            if ($document && PaymentQuote::reused($row)) \App\Services\Payments\Classification::error('payment', 'El abono debe ser un cobro nuevo.');
            if (PaymentQuote::reused($row)) continue;
            $method = PaymentMethodType::query()->find($row['payment_method_type_id']);
            if (!$method || !$method->is_active) \App\Services\Payments\Classification::error('payment_method_type_id', 'La forma de pago seleccionada no está disponible.');
            if ($method->collection_kind === 'account') {
                $account = \App\Models\Tenant\BankAccount::query()->find($method->bank_account_id);
                if (!$account || !$account->status || $account->currency_type_id !== $method->currency_type_id) {
                    \App\Services\Payments\Classification::error('payment_method_type_id', 'La cuenta configurada en el método no está disponible.');
                }
            }
            MethodCurrency::assertActiveCurrencies($company->getConnection(), [$method->currency_type_id]);
            MethodCurrency::resolve((string)$method->currency_type_id, $currency, $row);
        }
        $quote = PaymentQuote::calculate($data['payments'], $currency, $rate, $payable,
            fn ($date)=>FiscalProfileService::at($company, $date), (bool)$company->igtf_enabled, $company->igtf_rate, (bool)$document);
        if ($document && array_filter($quote['payments'], fn ($row)=>D::of($row['tax_amount'])->isPositive())) {
            FiscalDocumentPersistence::assertLaterIgtfAvailable($document);
        }
        return ['data'=>$quote + ['igtf_enabled'=>(bool)$company->igtf_enabled, 'igtf_rate'=>$company->igtf_rate]];
    }
}
