<?php

namespace Modules\Accounting\Http\Controllers;

use Illuminate\Http\Request;
use Modules\Accounting\Domain\{ApiProblem, CompanyContext};
use Modules\Accounting\Models\{OpenItem, OpeningBatch, Settlement, JournalEntry};
use Modules\Accounting\Services\{OpeningService, SettlementService, OperationService, EntryService, OpenItemReader};
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
        return app(OpenItemReader::class)->one($item);
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
        $page->setCollection(app(OpenItemReader::class)->many($page->getCollection()));
        return $page;
    }
    public function show(Request $request, string $company, string $id): array
    {
        $item = OpenItem::findOrFail($id);
        return ['data' => app(OpenItemReader::class)->one($item, true)];
    }
    public function opening(): array
    {
        $batch = OpeningBatch::orderByRaw('active_key IS NULL ASC')->orderByDesc('created_at')->orderByDesc('id')->first();
        return ['data' => $batch, 'entry' => $batch ? app(EntryService::class)->resource(JournalEntry::findOrFail($batch->journal_entry_id)) : null, 'migration_date' => app(CompanyContext::class)->company()->migration_date];
    }
    public function proposeOpening(Request $request): array
    {
        $input = $request->validate(\Modules\Accounting\Support\OpeningInput::rules());
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
