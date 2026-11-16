<?php

namespace Modules\Accounting\Services;

use App\Models\Tenant\User;
use Brick\Math\BigDecimal;
use Illuminate\Support\Str;
use Modules\Accounting\Domain\{ApiProblem, CompanyContext, Permissions};
use Modules\Accounting\Models\{ReportJob, ReportArtifact, Account, ThirdParty, CostCenter};
use Modules\Accounting\Support\{ReportInput, ReportSchema, TenantDB as DB};

/** Capture once under a consistent read; render and serve only immutable files. */
final class ReportExportService
{
    private const MAX_BYTES = 104857600;

    private function authorize(int $id): User
    {
        $user = User::without('establishment')->whereKey($id)->where('active', true)->where('locked', false)->first();
        if (!$user) {
            throw new ApiProblem('FORBIDDEN_ACTION', 'La cuenta de SAB ya no puede consultar estos informes.', 403);
        }
        app(Permissions::class)->require($user, 'reports');
        return $user;
    }

    private function directory(string $id): string
    {
        $c = app(CompanyContext::class);
        return rtrim(config('accounting.private_storage', storage_path('app/accounting/private')), '/') . '/reports/' . $c->organizationId() . '/' . $c->id() . '/' . $id;
    }

    private function encoded(array $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) . "\n";
    }

    private function filters(array $filters): array
    {
        $labels = ['from' => 'Desde', 'to' => 'Hasta / corte', 'compare_from' => 'Comparativo desde', 'compare_to' => 'Comparativo hasta', 'side' => 'Por cobrar / pagar', 'status' => 'Estado', 'include_pending' => 'Incluir documentos pendientes'];
        $out = [];
        foreach ($labels as $key => $label) {
            if (array_key_exists($key, $filters)) {
                $out[$label] = is_bool($filters[$key]) ? ($filters[$key] ? 'Sí' : 'No') : (string) $filters[$key];
            }
        }
        foreach (['account_id' => [Account::class, 'Cuenta'], 'third_party_id' => [ThirdParty::class, 'Tercero'], 'cost_center_id' => [CostCenter::class, 'Centro de costo']] as $key => [$model, $label]) {
            if (isset($filters[$key])) {
                $record = $model::findOrFail($filters[$key]);
                $out[$label] = ($record->code ?? $record->tax_id ?? '') . ' · ' . $record->name . ' · ' . $record->id;
            }
        }
        return $out;
    }

    public function capture(string $kind, array $input, string $token, int $user): array
    {
        ReportSchema::title($kind);
        $written = null;
        try {
            return DB::transaction(function () use ($kind, $input, $token, $user, &$written) {
                $c = app(CompanyContext::class);
                DB::table('accounting_companies')->where('organization_id', $c->organizationId())->where('id', $c->id())->lockForUpdate()->first();
                $actor = $this->authorize($user);
                $f = ReportInput::normalize($kind, $input);
                $reports = app(ReportService::class);
                $report = $reports->build($kind, [...$f, 'page' => 1, 'per_page' => 1]);
                if (!hash_equals($report['review_token'], $token)) {
                    throw new ApiProblem('REPORT_VIEW_CHANGED', 'El libro, sus filtros o la presentación cambiaron desde la consulta. Actualiza el informe antes de conservar otra versión.', 412);
                }
                $reports->assertBookPrecision($kind, $f);
                $id = (string) Str::uuid();
                $directory = $this->directory($id);
                if (!mkdir($directory, 0700, true) && !is_dir($directory)) {
                    throw new \RuntimeException('No se puede preparar el archivo privado del informe.');
                }
                $path = $directory . '/data.jsonl';
                $handle = fopen($path, 'xb');
                if (!$handle) {
                    throw new \RuntimeException('No se puede conservar el informe.');
                }
                $written = $path;
                chmod($path, 0600);
                $header = $report;
                unset($header['rows'], $header['page'], $header['per_page'], $header['last_page']);
                $header['export_id'] = $id;
                $header['audit_sequence_at_capture'] = (int) (DB::table('accounting_audit_heads')->where('organization_id', $c->organizationId())->where('company_id', $c->id())->value('last_sequence') ?? 0);
                $header['created_by'] = ['id' => $user, 'name' => $actor->name];
                $header['filter_labels'] = $this->filters($f);
                $header['presentation'] = $reports->presentation();
                $hash = hash_init('sha256');
                $bytes = 0;
                $write = function (array $record) use ($handle, $hash, &$bytes) {
                    $line = $this->encoded($record);
                    $bytes += strlen($line);
                    if ($bytes > self::MAX_BYTES) {
                        throw new ApiProblem('REPORT_EXPORT_SIZE', 'El informe supera 100 MiB. Selecciona un intervalo o auxiliar menor; no se genera una copia parcial.', 413);
                    }
                    if (fwrite($handle, $line) !== strlen($line)) {
                        throw new \RuntimeException('No se pudo conservar el informe completo.');
                    }
                    hash_update($hash, $line);
                };
                $count = 0;
                $debit = BigDecimal::zero();
                $credit = BigDecimal::zero();
                $book = BigDecimal::zero();
                try {
                    $write(['type' => 'header', 'schema' => 2, 'report' => $header]);
                    foreach ($reports->iterate($kind, $f, $report) as $row) {
                        if (array_key_exists('debit', $row)) {
                            $debit = $debit->plus($row['debit']);
                            $credit = $credit->plus($row['credit']);
                        }
                        if ($kind === 'open-items') {
                            $book = $book->plus($row['remaining_book']);
                        }
                        $write(['type' => 'row', 'row' => $row]);
                        ++$count;
                    }
                    if (isset($report['total']) && $count !== $report['total'] || !isset($report['total']) && $count !== count($report['rows'])) {
                        throw new ApiProblem('REPORT_INCOMPLETE', 'La lectura no coincide con el número de filas consultado. No se publicó una copia parcial.', 409);
                    }
                    if (in_array($kind, ['journal', 'ledger', 'auxiliary'], true) && (!$debit->isEqualTo($report['totals']['debit']) || !$credit->isEqualTo($report['totals']['credit']))) {
                        throw new ApiProblem('REPORT_TOTAL_MISMATCH', 'Las filas no concilian con los controles del libro. No se publicó la descarga.', 409);
                    }
                    if ($kind === 'open-items' && !$book->isEqualTo($report['totals']['book'])) {
                        throw new ApiProblem('REPORT_TOTAL_MISMATCH', 'Las partidas no concilian con su saldo al corte. No se publicó la descarga.', 409);
                    }
                    $write(['type' => 'footer', 'rows' => $count, 'totals' => $report['totals']]);
                } finally {
                    fclose($handle);
                }
                chmod($path, 0400);
                $digest = hash_final($hash);
                $meta = $header;
                unset($meta['presentation'], $meta['opening_groups']);
                $meta['opening_groups_count'] = count($header['opening_groups'] ?? []);
                $job = ReportJob::create(['id' => $id, 'user_id' => $user, 'kind' => $kind, 'format' => 'bundle', 'filters' => $f, 'status' => 'building', 'schema_version' => 2]);
                $job->fill(['path' => $path, 'sha256' => $digest, 'row_count' => $count, 'snapshot' => $meta, 'view_token' => $token, 'status' => 'ready']);
                $job->save();
                app(AuditService::class)->record($user, 'report.captured', 'report_job', $id, ['kind' => $kind, 'filters' => $f, 'data_version' => $report['data_version'], 'snapshot_sha256' => $digest, 'rows' => $count, 'totals' => $report['totals']]);
                return $this->resource($job);
            });
        } catch (\Throwable $error) {
            if ($written && is_file($written)) {
                unlink($written);
            }
            throw $error;
        }
    }

    private function readable(ReportJob $job): string
    {
        $path = $this->directory($job->id) . '/data.jsonl';
        if ($job->status !== 'ready' || $job->schema_version !== 2 || $job->path !== $path || !is_file($path) || is_link($path) || filesize($path) > self::MAX_BYTES) {
            throw new ApiProblem('REPORT_SNAPSHOT_MISSING', 'La copia conservada no está disponible. Revisa o restaura su respaldo antes de continuar.', 409);
        }
        if (!hash_equals($job->sha256, hash_file('sha256', $path))) {
            throw new ApiProblem('REPORT_SNAPSHOT_CHANGED', 'La huella del informe conservado no coincide. No se genera otra descarga con datos alterados.', 409);
        }
        return $path;
    }

    /** Reads only the sealed snapshot. No report query is run while rendering. */
    public function read(ReportJob $job): \Generator
    {
        $path = $this->readable($job);
        $handle = fopen($path, 'rb');
        if (!$handle) {
            throw new ApiProblem('REPORT_SNAPSHOT_MISSING', 'No se puede abrir el informe conservado.', 409);
        }
        $hash = hash_init('sha256');
        $count = 0;
        $header = false;
        $footer = false;
        try {
            while (($line = fgets($handle)) !== false) {
                hash_update($hash, $line);
                $record = json_decode($line, true, 64, JSON_THROW_ON_ERROR);
                if (!$header) {
                    if (($record['type'] ?? '') !== 'header' || ($record['schema'] ?? 0) !== 2 || ($record['report']['export_id'] ?? '') !== $job->id) {
                        throw new ApiProblem('REPORT_SNAPSHOT_CHANGED', 'La cabecera del informe no coincide con su identidad.', 409);
                    }
                    $header = true;
                } elseif (($record['type'] ?? '') === 'row' && !$footer) {
                    ++$count;
                } elseif (($record['type'] ?? '') === 'footer' && !$footer && ($record['rows'] ?? -1) === $job->row_count && $count === $job->row_count) {
                    $footer = true;
                } else {
                    throw new ApiProblem('REPORT_SNAPSHOT_CHANGED', 'La estructura del informe no coincide con la versión conservada.', 409);
                }
                yield $record;
            }
            if (!$header || !$footer || !hash_equals($job->sha256, hash_final($hash))) {
                throw new ApiProblem('REPORT_SNAPSHOT_CHANGED', 'El informe conservado está incompleto o cambió durante su lectura.', 409);
            }
        } finally {
            fclose($handle);
        }
    }

    public function resource(ReportJob $job): array
    {
        return [...$job->only(['id', 'kind', 'status', 'row_count', 'schema_version', 'sha256', 'created_at']), 'snapshot' => $job->snapshot, 'artifacts' => ReportArtifact::where('report_job_id', $job->id)->orderBy('format')->get()->map(fn($artifact) => $artifact->only(['id', 'format', 'sha256', 'bytes', 'renderer_revision', 'created_at']))->all()];
    }

    public function download(string $id, string $format, int $user): array
    {
        if (!in_array($format, ['pdf', 'xlsx', 'csv'], true)) {
            throw new ApiProblem('REPORT_FORMAT', 'Selecciona PDF, XLSX o CSV.');
        }
        $this->authorize($user);
        $job = ReportJob::findOrFail($id);
        $this->readable($job);
        $directory = $this->directory($id);
        $lock = fopen($directory . '/' . $format . '.lock', 'c');
        if (!$lock || !flock($lock, LOCK_EX)) {
            throw new \RuntimeException('No se puede preparar la descarga.');
        }
        $temporary = null;
        try {
            $artifact = ReportArtifact::where('report_job_id', $id)->where('format', $format)->first();
            if (!$artifact) {
                $temporary = $directory . '/' . (string) Str::uuid() . '.pending';
                $renderer = app(ReportRenderer::class);
                $renderer->write($job, $format, $temporary);
                $bytes = filesize($temporary);
                if (!$bytes || $bytes > self::MAX_BYTES) {
                    throw new ApiProblem('REPORT_ARTIFACT_SIZE', 'El archivo supera el tamaño permitido. Selecciona un intervalo menor; no se entrega una copia parcial.', 413);
                }
                $digest = hash_file('sha256', $temporary);
                $target = $directory . '/' . $format . '.bin';
                if (is_file($target)) {
                    throw new ApiProblem('REPORT_ARTIFACT_CONFLICT', 'Existe un archivo sin su registro de integridad. Revisa el respaldo antes de reemplazarlo.', 409);
                }
                if (!rename($temporary, $target)) {
                    throw new \RuntimeException('No se pudo conservar la descarga completa.');
                }
                $temporary = $target;
                chmod($target, 0400);
                $artifact = DB::transaction(function () use ($job, $format, $target, $digest, $bytes, $renderer, $user) {
                    DB::table('accounting_companies')->where('id', app(CompanyContext::class)->id())->lockForUpdate()->first();
                    $this->authorize($user);
                    return ReportArtifact::create(['report_job_id' => $job->id, 'format' => $format, 'path' => $target, 'sha256' => $digest, 'bytes' => $bytes, 'renderer_revision' => $renderer->revision(), 'created_by' => $user]);
                });
                $temporary = null;
            }
            $path = $directory . '/' . $format . '.bin';
            if ($artifact->path !== $path || !is_file($path) || is_link($path) || filesize($path) !== $artifact->bytes || !hash_equals($artifact->sha256, hash_file('sha256', $path))) {
                throw new ApiProblem('REPORT_ARTIFACT_CHANGED', 'La descarga conservada falta o no coincide con su huella. Restaura su respaldo; no se sustituye silenciosamente.', 409);
            }
            DB::transaction(function () use ($job, $artifact, $user) {
                DB::table('accounting_companies')->where('id', app(CompanyContext::class)->id())->lockForUpdate()->first();
                $this->authorize($user);
                app(AuditService::class)->record($user, 'report.downloaded', 'report_job', $job->id, ['format' => $artifact->format, 'snapshot_sha256' => $job->sha256, 'artifact_sha256' => $artifact->sha256, 'data_version' => $job->snapshot['data_version']]);
            });
            return ['path' => $path, 'sha256' => $artifact->sha256, 'snapshot_sha256' => $job->sha256, 'filename' => 'contabilidad-' . $job->kind . '-' . $job->snapshot['to'] . '-' . substr($job->id, 0, 8) . '.' . $format];
        } finally {
            if ($temporary && is_file($temporary)) {
                unlink($temporary);
            }
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
