<?php

namespace Modules\Accounting\Http\Controllers;

use Illuminate\Http\Request;
use Modules\Accounting\Domain\{ApiProblem, Permissions};
use Modules\Accounting\Models\ImportJob;
use Modules\Accounting\Services\{ImportService, OperationService};
use Modules\Accounting\Support\{ImportSchema, ImportFileReader, ImportWorkbook};

final class ImportController extends \App\Http\Controllers\Controller
{
    public function __construct(private ImportService $service)
    {
    }

    private function authorizeKind(Request $r, string $kind): void
    {
        app(Permissions::class)->require($r->user(), ImportSchema::definition($kind)['permission']);
    }

    public function index(Request $r): array
    {
        $v = $r->validate(['kind' => 'nullable|in:third-parties,entries,opening,documents']);
        return ['data' => ImportJob::when($v['kind'] ?? null, fn($q, $kind) => $q->where('kind', $kind))->orderByDesc('created_at')->limit(50)->get()->map(fn($job) => [...$job->only(['id', 'kind', 'filename', 'file_hash', 'status', 'version', 'created_at']), 'summary' => $job->result]), 'kinds' => ImportSchema::kinds(), 'limits' => ['bytes' => ImportSchema::MAX_BYTES, 'rows' => ImportSchema::MAX_ROWS, 'units' => ImportSchema::MAX_UNITS]];
    }

    public function template(Request $r, string $company, string $kind, string $format): mixed
    {
        $this->authorizeKind($r, $kind);
        $book = app(ImportWorkbook::class);
        return $this->download($format === 'csv' ? $book->csvTemplate($kind) : $book->template($kind), 'plantilla-' . $kind . '-v1.' . $format, $format);
    }

    public function preview(Request $r): array
    {
        $v = $r->validate(['kind' => 'required|in:third-parties,entries,opening,documents', 'file' => 'required|file|max:2048', 'delimiter' => 'required|in:semicolon,comma,tab', 'decimal_mark' => 'required|in:dot,comma']);
        $this->authorizeKind($r, $v['kind']);
        $file = $r->file('file');
        if (!$file->isValid()) {
            throw new ApiProblem('IMPORT_UPLOAD', 'No se recibió el archivo completo. Vuelve a seleccionarlo.');
        }
        $format = strtolower($file->getClientOriginalExtension());
        $options = ['format' => $format, 'delimiter' => ['semicolon' => ';', 'comma' => ',', 'tab' => "\t"][$v['delimiter']], 'decimal_mark' => $v['decimal_mark'] === 'comma' ? ',' : '.'];
        $rows = app(ImportFileReader::class)->read($file->getRealPath(), $v['kind'], $format, $options);
        return ['data' => $this->service->preview($v['kind'], $rows, hash_file('sha256', $file->getRealPath()), $file->getClientOriginalName(), $options, $r->user()->id)];
    }

    public function show(Request $r, string $company, string $id): array
    {
        return ['data' => $this->service->resource(ImportJob::findOrFail($id))];
    }

    public function unit(Request $r, string $company, string $id, string $unit): array
    {
        return ['data' => $this->service->rows(ImportJob::findOrFail($id), $unit)];
    }

    private function input(Request $r, bool $apply): array
    {
        $v = $r->validate(['preview_hash' => 'required|string|size:64|regex:/^[a-f0-9]+$/', ...($apply ? ['units' => 'required|array|min:1|max:200', 'units.*' => 'required|uuid|distinct'] : [])]);
        $version = trim((string) $r->header('If-Match'), '"');
        if (!preg_match('/^[1-9][0-9]{0,9}$/D', $version)) {
            throw new ApiProblem('VERSION_REQUIRED', 'Se requiere la versión de la validación.', 428);
        }
        return [...$v, 'version' => (int) $version];
    }

    public function revalidate(Request $r, string $company, string $id): array
    {
        $job = ImportJob::findOrFail($id);
        $this->authorizeKind($r, $job->kind);
        $v = ['id' => $id, ...$this->input($r, false)];
        return ['data' => app(OperationService::class)->execute($r->user()->id, 'import.revalidate', $r->header('Idempotency-Key'), $v, fn() => $this->service->revalidate($id, $v['version'], $v['preview_hash'], $r->user()->id))];
    }

    public function apply(Request $r, string $company, string $id): array
    {
        $job = ImportJob::findOrFail($id);
        $this->authorizeKind($r, $job->kind);
        $v = ['id' => $id, ...$this->input($r, true)];
        return ['data' => app(OperationService::class)->execute($r->user()->id, 'import.apply', $r->header('Idempotency-Key'), $v, fn() => $this->service->apply($id, $v['version'], $v['preview_hash'], $v['units'], $r->user()->id))];
    }

    public function errors(Request $r, string $company, string $id): mixed
    {
        $job = ImportJob::findOrFail($id);
        return $this->download(app(ImportWorkbook::class)->errors($job), 'revision-importacion-' . $job->id . '.xlsx', 'xlsx');
    }

    private function download(string $bytes, string $filename, string $format): mixed
    {
        return response($bytes, 200, ['Content-Type' => $format === 'csv' ? 'text/csv; charset=UTF-8' : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'Content-Disposition' => 'attachment; filename="' . $filename . '"', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
