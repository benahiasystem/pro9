<?php
namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\{Company, Document, Person, User};
use App\Services\Fiscal\{FiscalAmounts, FiscalProfileService, SpecialTaxpayerPolicy};
use Illuminate\Http\Request;

class FiscalProfileController extends Controller
{
    private function entity(string $kind, int $id)
    {
        abort_unless(in_array($kind, ['company','customer'], true), 404);
        return $kind === 'company' ? Company::findOrFail($id) : Person::whereType('customers')->findOrFail($id);
    }

    public function record(string $kind, int $id)
    {
        $entity = $this->entity($kind, $id);
        $db = $entity->getConnection();
        $history = $db->getSchemaBuilder()->hasTable('fiscal_profile_versions')
            ? $db->table('fiscal_profile_versions')->where($kind === 'company' ? 'company_id' : 'person_id', $id)->orderByDesc('id')->get() : [];
        return ['data'=>FiscalProfileService::at($entity, now()->format('Y-m-d')), 'history'=>$history,
            'can_edit'=>auth()->user() instanceof User && auth()->user()->type === 'admin' && $db->getSchemaBuilder()->hasTable('fiscal_profile_versions')];
    }

    public function store(Request $request, string $kind, int $id)
    {
        return ['success'=>true, 'data'=>FiscalProfileService::store($this->entity($kind, $id), $request->all()), 'message'=>'Nueva versión fiscal guardada.'];
    }

    public function evaluate(Request $request)
    {
        $data = $request->validate(['customer_id'=>'nullable|integer', 'document_id'=>'nullable|integer',
            'date'=>'required|date_format:Y-m-d', 'currency_type_id'=>'required|in:VES,USD',
            'financial_intermediation'=>'nullable|in:none,financial_institution', 'original_amount'=>'nullable|numeric|min:0',
            'fiscal_context'=>'nullable|array']);
        $document = !empty($data['document_id']) ? Document::findOrFail($data['document_id']) : null;
        if ($document) DocumentFiscalController::authorizeDocument($document);
        $issuer = FiscalProfileService::at(Company::firstOrFail(), $data['date']);
        $customerId = $document ? $document->customer_id : ($data['customer_id'] ?? null);
        $buyer = $customerId ? FiscalProfileService::at(Person::whereType('customers')->findOrFail($customerId), $data['date']) : [];
        // A proposal is informational. Every collection and voucher is revalidated under locks.
        $input = ['financial_intermediation'=>$data['financial_intermediation'] ?? null];
        $needsIntermediation = $data['currency_type_id'] === 'USD' && SpecialTaxpayerPolicy::spe($issuer, $data['date']) && empty($input['financial_intermediation']);
        $decision = $needsIntermediation ? null : SpecialTaxpayerPolicy::payment($issuer, $input, $data['currency_type_id'], $data['date']);
        $company = Company::firstOrFail();
        $tax = ($decision['igtf_status'] ?? '') === 'subject' && $company->igtf_enabled && $company->igtf_rate > 0
            ? round(($data['original_amount'] ?? 0) * $company->igtf_rate / 100, 2) : 0;
        return ['data'=>['issuer'=>$issuer, 'buyer'=>$buyer, 'payment'=>$decision, 'tax_amount'=>$tax,
            'issuer_spe'=>SpecialTaxpayerPolicy::spe($issuer, $data['date']), 'buyer_spe'=>SpecialTaxpayerPolicy::spe($buyer, $data['date']),
            'igtf_enabled'=>(bool)$company->igtf_enabled, 'igtf_rate'=>$company->igtf_rate,
            'needs_intermediation'=>$needsIntermediation, 'retention'=>SpecialTaxpayerPolicy::retention($issuer, $buyer, $data['date'], $data['fiscal_context'] ?? [])]];
    }
}
