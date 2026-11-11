<?php

namespace Modules\Accounting\Http\Controllers;

use Illuminate\Http\Request;
use Modules\Accounting\Domain\{ApiProblem, CompanyContext};
use Modules\Accounting\Models\{OpenItem, ThirdParty, Account, OpeningBatch, Settlement, SettlementAllocation, JournalEntry};
use Modules\Accounting\Services\{OpeningService, SettlementService, OperationService, EntryService};
final class AuxiliaryController extends \App\Http\Controllers\Controller
{
    private function version(Request $request): ?int
    {
        if (!$request->hasHeader('If-Match')) {
            return null;
        }
        $value = trim((string) $request->header('If-Match'), '"');
        if (!preg_match('/^[1-9][0-9]{0,9}$/D', $value)) {
            throw new ApiProblem('VERSION_REQUIRED', 'Revisa la versión de la propuesta antes de modificarla.', 428);
        }
        return (int) $value;
    }
    private function resource(OpenItem $item): array
    {
        return [...$item->toArray(), 'party' => ThirdParty::find($item->third_party_id)?->only(['id', 'name', 'tax_id']), 'account' => Account::find($item->account_id)?->only(['id', 'code', 'name'])];
    }
    public function index(Request $request): mixed
    {
        $input = $request->validate(['side' => 'nullable|in:receivable,payable', 'status' => 'nullable|in:open,settled,reversed', 'third_party_id' => 'nullable|uuid', 'currency' => 'nullable|in:VES,USD,EUR', 'q' => 'nullable|string|max:100']);
        $query = OpenItem::query();
        foreach (['side', 'status', 'third_party_id', 'currency'] as $field) {
            if (!empty($input[$field])) {
                $query->where($field, $input[$field]);
            }
        }
        if (!empty($input['q'])) {
            $query->where('reference', 'like', '%' . $input['q'] . '%');
        }
        $page = $query->orderBy('due_date')->orderBy('issue_date')->orderBy('id')->paginate(25);
        $page->setCollection($page->getCollection()->map(fn($item) => $this->resource($item)));
        return $page;
    }
    public function show(Request $request, string $company, string $id): array
    {
        $item = OpenItem::findOrFail($id);
        $allocations = SettlementAllocation::where('open_item_id', $id)->orderBy('settlement_date')->get();
        return ['data' => [...$this->resource($item), 'allocations' => $allocations->map(fn($a) => [...$a->toArray(), 'entry' => JournalEntry::find($a->journal_entry_id)?->only(['id', 'reference', 'status', 'posted_at'])])]];
    }
    public function opening(): array
    {
        $batch = OpeningBatch::orderByRaw('active_key IS NULL ASC')->orderByDesc('created_at')->orderByDesc('id')->first();
        return ['data' => $batch, 'entry' => $batch ? app(EntryService::class)->resource(JournalEntry::findOrFail($batch->journal_entry_id)) : null, 'migration_date' => app(CompanyContext::class)->company()->migration_date];
    }
    public function proposeOpening(Request $request): array
    {
        $input = $request->validate(['cutoff_date' => 'required|date_format:Y-m-d', 'reason' => 'required|string|min:10|max:300', 'evidence_reference' => 'required|string|min:5|max:1000', 'balances' => 'required|array|min:2|max:500', 'balances.*' => 'array:account_id,third_party_id,debit,credit', 'balances.*.account_id' => 'required|uuid', 'balances.*.third_party_id' => 'nullable|uuid', 'balances.*.debit' => 'required|string|max:40', 'balances.*.credit' => 'required|string|max:40', 'open_items' => 'present|array|max:2000', 'open_items.*' => 'array:account_id,third_party_id,source_document_id,reference,issue_date,due_date,currency,original_amount,book_amount,exchange_rate,exchange_date,exchange_source,evidence_reference', 'open_items.*.account_id' => 'required|uuid', 'open_items.*.third_party_id' => 'required|uuid', 'open_items.*.source_document_id' => 'nullable|uuid', 'open_items.*.reference' => 'required|string|max:100', 'open_items.*.issue_date' => 'required|date_format:Y-m-d', 'open_items.*.due_date' => 'nullable|date_format:Y-m-d', 'open_items.*.currency' => 'required|in:VES,USD,EUR', 'open_items.*.original_amount' => 'required|string|max:40', 'open_items.*.book_amount' => 'required|string|max:40', 'open_items.*.exchange_rate' => 'required|string|max:40', 'open_items.*.exchange_date' => 'required|date_format:Y-m-d', 'open_items.*.exchange_source' => 'required|string|min:3|max:255', 'open_items.*.evidence_reference' => 'required|string|min:5|max:1000']);
        $version = $this->version($request);
        return ['data' => app(OperationService::class)->execute($request->user()->id, 'opening.propose', $request->header('Idempotency-Key'), [...$input, 'version' => $version], fn() => app(EntryService::class)->resource(app(OpeningService::class)->prepare($input, $request->user()->id, $version)))];
    }
    public function link(Request $request, string $company, string $id): array
    {
        $input = $request->validate(['document_id' => 'required|uuid', 'reason' => 'required|string|min:10|max:1000']);
        $version = $this->version($request);
        if (!$version) {
            throw new ApiProblem('VERSION_REQUIRED', 'Se requiere la versión de la partida.', 428);
        }
        return ['data' => $this->resource(app(OpeningService::class)->linkAntecedent($id, $input['document_id'], $version, $request->user()->id, $input['reason']))];
    }
    public function settlements(): array
    {
        return ['data' => Settlement::orderByDesc('created_at')->limit(100)->get()->map(fn($record) => [...$record->toArray(), 'entry' => app(EntryService::class)->resource(JournalEntry::findOrFail($record->journal_entry_id))])];
    }
    public function proposeSettlement(Request $request): array
    {
        $input = $request->validate(['entry_id' => 'nullable|uuid', 'direction' => 'required|in:receipt,payment', 'accounting_date' => 'required|date_format:Y-m-d', 'reference' => 'required|string|max:100', 'third_party_id' => 'required|uuid', 'cash_account_id' => 'required|uuid', 'currency' => 'required|in:VES,USD,EUR', 'amount' => 'required|string|max:40', 'exchange_rate' => 'required|string|max:40', 'exchange_date' => 'required|date_format:Y-m-d', 'exchange_source' => 'required|string|min:3|max:255', 'evidence_reference' => 'required|string|min:5|max:1000', 'rate_reason' => 'required|string|min:10|max:1000', 'tax_review_reference' => 'required|string|min:10|max:2000', 'gain_account_id' => 'nullable|uuid', 'loss_account_id' => 'nullable|uuid', 'applications' => 'required|array|min:1|max:100', 'applications.*' => 'array:open_item_id,original_amount,measurement_rate', 'applications.*.open_item_id' => 'required|uuid', 'applications.*.original_amount' => 'required|string|max:40', 'applications.*.measurement_rate' => 'nullable|string|max:40']);
        if ($input['exchange_date'] > $input['accounting_date']) {
            throw new ApiProblem('FUTURE_SETTLEMENT_RATE', 'La tasa debe corresponder a la liquidación o a un antecedente documentado.');
        }
        $version = $this->version($request);
        return ['data' => app(OperationService::class)->execute($request->user()->id, 'settlement.propose', $request->header('Idempotency-Key'), [...$input, 'version' => $version], fn() => app(EntryService::class)->resource(app(SettlementService::class)->prepare($input, $request->user()->id, $version)))];
    }
}
