<?php

namespace Modules\Accounting\Http\Controllers;

use Illuminate\Http\Request;
use Modules\Accounting\Domain\ApiProblem;
use Modules\Accounting\Models\SourceDocument;
use Modules\Accounting\Services\{DocumentService, OperationService, EntryService, SecurityService};
final class DocumentController extends \App\Http\Controllers\Controller
{
    public function __construct(private DocumentService $service)
    {
    }
    private function input(Request $request): array
    {
        return $request->validate(['kind' => 'required|in:sale_invoice,purchase_invoice,sale_credit_note,sale_debit_note,purchase_credit_note,purchase_debit_note', 'number' => 'nullable|string|max:100', 'fiscal_control' => 'nullable|string|max:100', 'issue_date' => 'required|date_format:Y-m-d', 'fiscal_date' => 'required|date_format:Y-m-d', 'received_on' => 'nullable|date_format:Y-m-d', 'due_date' => 'nullable|date_format:Y-m-d', 'third_party_id' => 'nullable|uuid', 'related_document_id' => 'nullable|uuid', 'currency' => 'required|in:VES,USD,EUR', 'exchange_rate' => 'required|string|max:40', 'exchange_source' => 'nullable|string|max:255', 'exchange_date' => 'required|date_format:Y-m-d', 'rate_evidence_reference' => 'nullable|string|max:1000', 'rate_reason' => 'nullable|string|max:2000', 'tax_explanation' => 'nullable|string|max:2000', 'amounts' => 'required|array', 'amounts.base' => 'required|string|max:40', 'amounts.vat' => 'required|string|max:40', 'amounts.other_taxes' => 'sometimes|required|string|max:40', 'amounts.total' => 'required|string|max:40', ...$this->taxFields()]);
    }
    private function taxFields(): array
    {
        return ['tax_context' => 'sometimes|array:currency,payment_currency,scope', 'tax_context.currency' => 'sometimes|in:VES,USD,EUR', 'tax_context.payment_currency' => 'sometimes|in:VES,USD,EUR', 'tax_context.scope' => 'sometimes|string|max:100', 'tax_components' => 'sometimes|array|max:25', 'tax_components.*.tax' => 'required|in:IVA,IGTF,OTHER', 'tax_components.*.rule_id' => 'nullable|uuid', 'tax_components.*.base' => 'required|string|max:40', 'tax_components.*.amount' => 'required|string|max:40', 'tax_components.*.manual_reason' => 'nullable|string|max:2000'];
    }
    private function version(Request $request): int
    {
        $version = trim((string) $request->header('If-Match'), '"');
        if (!preg_match('/^[1-9][0-9]{0,9}$/D', $version)) {
            throw new ApiProblem('VERSION_REQUIRED', 'Se requiere la versión actual del documento.', 428);
        }
        return (int) $version;
    }
    public function index(Request $request): mixed
    {
        $input = $request->validate(['q' => 'nullable|string|max:100', 'status' => 'nullable|in:draft,received,validated,proposed,posted,changed,observed,excluded,opening_antecedent', 'kind' => 'nullable|string|max:40', 'from' => 'nullable|date_format:Y-m-d', 'to' => 'nullable|date_format:Y-m-d']);
        $query = SourceDocument::query();
        foreach (['status', 'kind'] as $field) {
            if (!empty($input[$field])) {
                $query->where($field, $input[$field]);
            }
        }
        if (!empty($input['q'])) {
            $query->where('number', 'like', '%' . $input['q'] . '%');
        }
        if (!empty($input['from'])) {
            $query->where('fiscal_date', '>=', $input['from']);
        }
        if (!empty($input['to'])) {
            $query->where('fiscal_date', '<=', $input['to']);
        }
        $page = $query->orderByDesc('issue_date')->orderByDesc('created_at')->paginate(25);
        $page->setCollection($page->getCollection()->map(fn($document) => $this->service->resource($document)));
        return $page;
    }
    public function show(Request $request, string $company, string $id): array
    {
        return ['data' => $this->service->resource(SourceDocument::findOrFail($id))];
    }
    public function store(Request $request): array
    {
        $input = $this->input($request);
        return ['data' => app(OperationService::class)->execute($request->user()->id, 'document.create', $request->header('Idempotency-Key'), $input, fn() => $this->service->resource($this->service->create($input, $request->user()->id)))];
    }
    public function update(Request $request, string $company, string $id): array
    {
        return ['data' => $this->service->resource($this->service->edit($id, $this->input($request), $this->version($request), $request->user()->id))];
    }
    public function validateDocument(Request $request, string $company, string $id): array
    {
        app(SecurityService::class)->requireVerified($request);
        $input = $request->validate(['source_confirmed' => 'sometimes|boolean', 'fiscal_date' => 'sometimes|required|date_format:Y-m-d', 'received_on' => 'nullable|date_format:Y-m-d', 'related_document_id' => 'nullable|uuid', 'exchange_rate' => 'sometimes|required|string|max:40', 'exchange_date' => 'sometimes|required|date_format:Y-m-d', 'exchange_source' => 'nullable|string|max:255', 'rate_evidence_reference' => 'nullable|string|max:1000', 'tax_explanation' => 'nullable|string|max:2000', 'rate_reason' => 'nullable|string|max:2000', ...$this->taxFields()]);
        return ['data' => $this->service->resource($this->service->validate($id, $this->version($request), $input, $request->user()->id))];
    }
    public function propose(Request $request, string $company, string $id): array
    {
        $input = $request->validate(['accounting_date' => 'required|date_format:Y-m-d', 'accounts' => 'sometimes|array:counterparty,base,vat,other_taxes,rounding', 'accounts.*' => 'nullable|uuid', 'replace_existing' => 'sometimes|boolean', 'reason' => 'nullable|string|min:10|max:2000']);
        return ['data' => app(EntryService::class)->resource($this->service->propose($id, $this->version($request), $input['accounting_date'], array_filter($input['accounts'] ?? []), $request->user()->id, $input))];
    }
}
