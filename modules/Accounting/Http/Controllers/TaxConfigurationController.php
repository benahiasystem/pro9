<?php

namespace Modules\Accounting\Http\Controllers;

use Illuminate\Http\Request;
use Modules\Accounting\Domain\ApiProblem;
use Modules\Accounting\Models\{TaxProfile, RuleSet};
use Modules\Accounting\Services\TaxConfigurationService;
final class TaxConfigurationController extends \App\Http\Controllers\Controller
{
    public function index(): array
    {
        return ['profiles' => TaxProfile::orderByDesc('effective_from')->get(), 'rules' => RuleSet::orderBy('code')->orderByDesc('version')->get()];
    }
    public function profile(Request $request, TaxConfigurationService $service): array
    {
        $input = $request->validate(['profile' => 'required|in:ordinary,formal,exempt', 'effective_from' => 'required|date_format:Y-m-d', 'effective_until' => 'nullable|date_format:Y-m-d', 'source_reference' => 'required|string|min:5|max:255', 'details' => 'required|array:input_tax_treatment,spe,notification_reference,retention_agent,notes', 'details.input_tax_treatment' => 'required|in:deductible,cost', 'details.spe' => 'required|boolean', 'details.notification_reference' => 'nullable|string|max:1000', 'details.retention_agent' => 'sometimes|boolean', 'details.notes' => 'nullable|string|max:2000']);
        return ['data' => $service->addProfile($input, $request->user()->id)];
    }
    public function rule(Request $request, TaxConfigurationService $service): array
    {
        $input = $request->validate(['code' => 'required|string|max:60', 'name' => 'required|string|max:255', 'tax' => 'required|in:IVA,IGTF,ISLR,IVA_RETENTION,OTHER', 'scenario' => 'required|string|max:100', 'rate_percent' => 'nullable|string|max:40', 'published_on' => 'nullable|date_format:Y-m-d', 'effective_from' => 'required|date_format:Y-m-d', 'effective_until' => 'nullable|date_format:Y-m-d', 'source_reference' => 'required|string|min:5|max:255', 'source_url' => 'nullable|url|max:1000', 'conditions' => 'sometimes|array:currency,payment_currency,scope', 'conditions.currency' => 'sometimes|array|min:1', 'conditions.currency.*' => 'in:VES,USD,EUR', 'conditions.payment_currency' => 'sometimes|array|min:1', 'conditions.payment_currency.*' => 'in:VES,USD,EUR', 'conditions.scope' => 'sometimes|string|max:100', 'notes' => 'nullable|string|max:2000']);
        return ['data' => $service->createRule($input, $request->user()->id)];
    }
    public function approve(Request $request, string $company, string $id, TaxConfigurationService $service): array
    {
        $version = trim((string) $request->header('If-Match'), '"');
        if (!ctype_digit($version) || (int) $version < 1) {
            throw new ApiProblem('VERSION_REQUIRED', 'Revisa la versión de la regla antes de aprobarla.', 428);
        }
        return ['data' => $service->approveRule($id, (int) $version, $request->user()->id)];
    }
}
