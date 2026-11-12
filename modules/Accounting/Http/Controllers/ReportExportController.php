<?php

namespace Modules\Accounting\Http\Controllers;

use Illuminate\Http\Request;
use Modules\Accounting\Models\ReportJob;
use Modules\Accounting\Services\{ReportExportService, OperationService};
use Modules\Accounting\Support\{ReportSchema, ReportInput};

final class ReportExportController extends \App\Http\Controllers\Controller
{
    public function __construct(private ReportExportService $service)
    {
    }

    public function index(Request $r): array
    {
        $v = $r->validate(['kind' => 'nullable|in:' . implode(',', array_keys(ReportSchema::kinds())), 'page' => 'nullable|integer|min:1|max:100000', 'per_page' => 'nullable|integer|min:1|max:100']);
        $jobs = ReportJob::where('status', 'ready')->when($v['kind'] ?? null, fn($q, $kind) => $q->where('kind', $kind))->orderByDesc('created_at')->orderByDesc('id')->paginate($v['per_page'] ?? 20);
        return ['data' => $jobs->getCollection()->map(fn($job) => $this->service->resource($job)), 'meta' => ['total' => $jobs->total(), 'page' => $jobs->currentPage(), 'last_page' => $jobs->lastPage()]];
    }

    public function capture(Request $r): array
    {
        $v = $r->validate(['kind' => 'required|in:' . implode(',', array_keys(ReportSchema::kinds())), 'filters' => 'required|array:' . implode(',', array_keys(ReportInput::rules())), 'review_token' => 'required|string|size:64|regex:/^[a-f0-9]+$/']);
        $filters = ReportInput::normalize($v['kind'], $v['filters']);
        $payload = ['kind' => $v['kind'], 'filters' => $filters, 'review_token' => $v['review_token']];
        return ['data' => app(OperationService::class)->execute($r->user()->id, 'report.capture', $r->header('Idempotency-Key'), $payload, fn() => $this->service->capture($v['kind'], $filters, $v['review_token'], $r->user()->id))];
    }

    public function show(Request $r, string $company, string $id): array
    {
        return ['data' => $this->service->resource(ReportJob::findOrFail($id))];
    }

    public function rows(Request $r, string $company, string $id): array
    {
        $v = $r->validate(['page' => 'nullable|integer|min:1|max:100000', 'per_page' => 'nullable|integer|min:1|max:500']);
        $job = ReportJob::findOrFail($id); $per = $v['per_page'] ?? 100; $page = $v['page'] ?? 1; $offset = ($page - 1) * $per;
        $rows = []; $number = 0; $header = null;
        foreach ($this->service->read($job) as $record) {
            if ($record['type'] === 'header') { $header = $record['report']; unset($header['presentation']); }
            elseif ($record['type'] === 'row' && ++$number > $offset && count($rows) < $per) { $rows[] = $record['row']; }
        }
        return ['data' => [...$header, 'rows' => $rows, 'total' => $job->row_count, 'page' => $page, 'per_page' => $per, 'last_page' => max(1, (int) ceil($job->row_count / $per)), 'export_id' => $job->id, 'snapshot_sha256' => $job->sha256]];
    }

    public function download(Request $r, string $company, string $id, string $format): mixed
    {
        $file = $this->service->download($id, $format, $r->user()->id);
        $mime = ['pdf' => 'application/pdf', 'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'csv' => 'text/csv; charset=UTF-8'][$format];
        return response()->download($file['path'], $file['filename'], ['Content-Type' => $mime, 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff', 'X-Accounting-Report' => $id, 'X-Accounting-Snapshot-SHA256' => $file['snapshot_sha256'], 'X-Accounting-Artifact-SHA256' => $file['sha256']]);
    }
}
