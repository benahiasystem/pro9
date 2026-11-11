<?php

namespace Modules\Accounting\Http\Controllers;

use Modules\Accounting\Domain\ApiProblem;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Services\{EntryService, OperationService};
use Illuminate\Http\Request;
final class EntryController extends \App\Http\Controllers\Controller
{
    public function __construct(private EntryService $service)
    {
    }
    public function index(Request $r): mixed
    {
        $v = $r->validate(['q' => 'nullable|string|max:100', 'status' => 'nullable|in:draft,review,observed,approved,posted,reversed', 'from' => 'nullable|date_format:Y-m-d', 'to' => 'nullable|date_format:Y-m-d', 'per_page' => 'nullable|integer|min:1|max:100']);
        $q = JournalEntry::query();
        if (!empty($v['q'])) {
            $q->where(fn($x) => $x->where('concept', 'like', '%' . $v['q'] . '%')->orWhere('reference', 'like', '%' . $v['q'] . '%'));
        }
        if (!empty($v['status'])) {
            if ($v['status'] === 'reversed') {
                $q->whereHas('reversal', fn($x) => $x->whereNotNull('posted_at'));
            } else {
                $q->where('status', $v['status']);
            }
        }
        if (!empty($v['from'])) {
            $q->whereDate('accounting_date', '>=', $v['from']);
        }
        if (!empty($v['to'])) {
            $q->whereDate('accounting_date', '<=', $v['to']);
        }
        return $q->orderByDesc('accounting_date')->orderByDesc('created_at')->paginate($v['per_page'] ?? 25);
    }
    private function version(Request $r): int
    {
        $v = trim((string) $r->header('If-Match'), '"');
        if (!preg_match('/^[1-9][0-9]{0,9}$/D', $v)) {
            throw new ApiProblem('VERSION_REQUIRED', 'Se requiere la versión del comprobante.', 428);
        }
        return (int) $v;
    }
    public function store(Request $r): array
    {
        $v = \Modules\Accounting\Support\EntryInput::validate($r);
        return ['data' => app(OperationService::class)->execute($r->user()->id, 'entry.create', $r->header('Idempotency-Key'), $v, fn() => $this->service->resource($this->service->create($v, $r->user()->id)))];
    }
    public function show(Request $r, string $company, string $id): array
    {
        return ['data' => $this->service->resource(JournalEntry::findOrFail($id))];
    }
    public function update(Request $r, string $company, string $id): array
    {
        return ['data' => $this->service->resource($this->service->edit($id, \Modules\Accounting\Support\EntryInput::validate($r), $this->version($r), $r->user()->id))];
    }
    public function submit(Request $r, string $company, string $id): array
    {
        return ['data' => $this->service->resource($this->service->transition($id, $this->version($r), $r->user()->id, 'submit'))];
    }
    public function observe(Request $r, string $company, string $id): array
    {
        $v = $r->validate(['comment' => 'required|string|max:2000']);
        return ['data' => $this->service->resource($this->service->transition($id, $this->version($r), $r->user()->id, 'observe', $v['comment']))];
    }
    public function approve(Request $r, string $company, string $id): array
    {
        $v = $r->validate(['comment' => 'nullable|string|max:2000']);
        return ['data' => $this->service->resource($this->service->transition($id, $this->version($r), $r->user()->id, 'approve', $v['comment'] ?? ''))];
    }
    public function post(Request $r, string $company, string $id): array
    {
        return ['data' => $this->service->post($id, $this->version($r), $r->user()->id, $r->header('Idempotency-Key'))];
    }
    public function reverse(Request $r, string $company, string $id): array
    {
        $v = $r->validate(['date' => 'required|date_format:Y-m-d', 'reason' => 'required|string|max:500']);
        return ['data' => $this->service->resource($this->service->prepareReversal($id, $v['date'], $v['reason'], $r->user()->id)), 'message' => 'Reverso preparado. Requiere revisión, aprobación y contabilización.'];
    }
}
