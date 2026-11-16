<?php

namespace Modules\Accounting\Services;

use Illuminate\Support\Str;
use Modules\Accounting\Domain\{ApiProblem, CompanyContext, Permissions};
use Modules\Accounting\Models\{ImportJob, ImportUnit, ImportReceipt};
use Modules\Accounting\Support\{ImportSchema, TenantDB as DB};

final class ImportService
{
    private function lockBook(): void
    {
        DB::table('accounting_companies')->where('id', app(CompanyContext::class)->id())->lockForUpdate()->first();
    }

    private function authorize(string $kind, int $id): void
    {
        $user = \App\Models\Tenant\User::without('establishment')->whereKey($id)->where('active', true)->where('locked', false)->first();
        if (!$user) {
            throw new ApiProblem('FORBIDDEN_ACTION', 'La cuenta de PRO9 ya no está disponible para importar.', 403);
        }
        app(Permissions::class)->require($user, 'import');
        app(Permissions::class)->require($user, ImportSchema::definition($kind)['permission']);
    }

    private function hash(array $value): string
    {
        return hash('sha256', json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }

    private function filePath(string $id): string
    {
        $context = app(CompanyContext::class);
        return rtrim(config('accounting.private_storage', storage_path('app/accounting/private')), '/') . '/imports/' . $context->organizationId() . '/' . $context->id() . '/' . $id . '.json';
    }

    private function dataset(ImportJob $job): array
    {
        $path = $this->filePath($job->id);
        if ($job->path !== $path || !is_file($path) || is_link($path) || filesize($path) > 16777216) {
            throw new ApiProblem('IMPORT_EVIDENCE_MISSING', 'No se encuentra la copia privada de este archivo. Recupera el respaldo antes de continuar.', 409);
        }
        $bytes = file_get_contents($path);
        if (!hash_equals($job->data_hash, hash('sha256', $bytes))) {
            throw new ApiProblem('IMPORT_EVIDENCE_CHANGED', 'La copia de validación no coincide con su huella original. No se aplicó la importación.', 409);
        }
        return json_decode($bytes, true, 64, JSON_THROW_ON_ERROR);
    }

    public function preview(string $kind, array $rows, string $fileHash, string $filename, array $options, int $user): array
    {
        ImportSchema::definition($kind);
        if (!preg_match('/^[a-f0-9]{64}$/D', $fileHash) || !$rows || count($rows) > ImportSchema::MAX_ROWS) {
            throw new \InvalidArgumentException('Dataset de importación inválido.');
        }
        $parameters = $this->hash(['schema' => ImportSchema::VERSION, 'kind' => $kind, 'file_hash' => $fileHash, 'options' => $options]);
        $written = null;
        try {
            return DB::transaction(function () use ($kind, $rows, $fileHash, $filename, $options, $user, $parameters, &$written) {
                $this->lockBook();
                $this->authorize($kind, $user);
                if ($existing = ImportJob::where('parameters_hash', $parameters)->first()) {
                    return $this->resource($existing);
                }
                $groups = [];
                foreach ($rows as $row) {
                    $reference = mb_strtoupper(trim($row['data']['referencia_importacion']));
                    $key = hash('sha256', $reference ?: 'missing-row:' . $row['row']);
                    $groups[$key][] = $row;
                }
                if (count($groups) > ImportSchema::MAX_UNITS) {
                    throw new ApiProblem('IMPORT_UNIT_LIMIT', 'Cada archivo admite hasta 200 referencias completas. Divide el lote sin separar sus comprobantes.');
                }
                $id = (string) Str::uuid();
                $path = $this->filePath($id);
                if (!is_dir(dirname($path)) && !mkdir(dirname($path), 0700, true) && !is_dir(dirname($path))) {
                    throw new \RuntimeException('No se puede preparar el almacenamiento privado de importación.');
                }
                $bytes = json_encode($groups, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
                $handle = fopen($path, 'xb');
                if (!$handle) {
                    throw new \RuntimeException('No se puede conservar la validación de importación.');
                }
                $written = $path;
                try {
                    if (fwrite($handle, $bytes) !== strlen($bytes)) {
                        throw new \RuntimeException('No se pudo conservar el archivo completo.');
                    }
                } finally {
                    fclose($handle);
                }
                chmod($path, 0400);
                $job = ImportJob::create(['id' => $id, 'user_id' => $user, 'kind' => $kind, 'file_hash' => $fileHash, 'filename' => mb_substr(basename(str_replace('\\', '/', $filename)), 0, 180), 'path' => $path, 'parameters_hash' => $parameters, 'data_hash' => hash('sha256', $bytes), 'options' => $options, 'status' => 'validating']);
                $position = 0;
                foreach ($groups as $key => $group) {
                    $canonical = array_map(function ($row) {
                        $data = $row['data'];
                        foreach (['empresa_rif', 'rif', 'rif_tercero'] as $field) {
                            if (isset($data[$field])) {
                                $data[$field] = ImportSchema::identity($data[$field]);
                            }
                        }
                        $data['referencia_importacion'] = mb_strtoupper($data['referencia_importacion']);
                        return $data;
                    }, $group);
                    ImportUnit::create(['import_job_id' => $job->id, 'reference' => mb_substr($group[0]['data']['referencia_importacion'], 0, 100), 'reference_hash' => $key, 'content_hash' => $this->hash($canonical), 'position' => ++$position, 'status' => 'invalid', 'rows' => array_column($group, 'row'), 'errors' => [], 'summary' => []]);
                }
                $this->validateUnits($job, $groups, $user);
                app(AuditService::class)->record($user, 'import.previewed', 'import_job', $job->id, ['kind' => $kind, 'file_hash' => $fileHash, 'summary' => $job->result]);
                return $this->resource($job);
            });
        } catch (\Throwable $e) {
            if ($written && is_file($written)) {
                unlink($written);
            }
            throw $e;
        }
    }

    private function domainErrors(\Throwable $error, array $rows): array
    {
        if (!$error instanceof ApiProblem) {
            // Infrastructure, programming and SQL failures roll back the entire
            // batch. They must never masquerade as a partially accepted file.
            throw $error;
        }
        return $error->details['errors'] ?? [ImportSchema::issue($rows[0]['row'], 'unidad', $error->errorCode, $error->getMessage())];
    }

    private function repeatedIdentities(string $kind, array $groups): array
    {
        $identities = [];
        foreach ($groups as $key => $rows) {
            $d = $rows[0]['data'];
            $identity = match ($kind) {
                'third-parties' => ImportSchema::identity($d['rif']),
                'documents' => app(DocumentService::class)->identity($d['tipo_documento'], $d['numero'], $d['rif_tercero'], $d['control_fiscal'] ?: null, $d['fecha_emision']),
                'opening' => 'one-opening-per-file',
                default => null,
            };
            if ($identity) {
                $identities[$identity][] = $key;
            }
        }
        return array_merge([], ...array_values(array_filter($identities, fn($keys) => count($keys) > 1)));
    }

    private function check(ImportJob $job, ImportUnit $unit, array $rows, int $user, bool $dryRun): void
    {
        $errors = array_merge([], ...array_column($rows, 'errors'));
        foreach ($rows as $row) {
            $ref = $row['data']['referencia_importacion'];
            if (strlen($ref) > 100 || trim($ref) === '') {
                $errors[] = ImportSchema::issue($row['row'], 'referencia_importacion', 'IMPORT_REFERENCE', 'Usa una referencia estable de entre 1 y 100 caracteres.');
            }
            if (ImportSchema::identity($row['data']['empresa_rif']) !== ImportSchema::identity(app(CompanyContext::class)->company()->tax_id)) {
                $errors[] = ImportSchema::issue($row['row'], 'empresa_rif', 'IMPORT_WRONG_COMPANY', 'El RIF del archivo no coincide con la empresa activa. No se trasladan filas entre empresas.');
            }
        }
        $unit->resource = null;
        $unit->summary = [];
        if (!$errors) {
            $receipt = ImportReceipt::where('kind', $job->kind)->where('reference_hash', $unit->reference_hash)->first();
            if ($receipt) {
                if ($receipt->content_hash === $unit->content_hash) {
                    $unit->fill(['status' => 'already_imported', 'errors' => [], 'resource' => $receipt->resource, 'summary' => ['accepted_in' => $receipt->import_job_id]]);
                    $unit->save();
                    return;
                }
                $errors[] = ImportSchema::issue($rows[0]['row'], 'referencia_importacion', 'IMPORT_REFERENCE_CONFLICT', 'Esta referencia ya fue aceptada con otros datos. Revisa el registro original; no se sobrescribe ni se duplica.');
            }
        }
        if (!$errors) {
            $level = DB::transactionLevel();
            DB::beginTransaction();
            try {
                $result = app(ImportAdapter::class)->execute($job->kind, $rows, $user);
                $unit->summary = $result['summary'];
                if (!$dryRun) {
                    $unit->resource = $result['resource'];
                    ImportReceipt::create(['import_job_id' => $job->id, 'reference' => $unit->reference, 'reference_hash' => $unit->reference_hash, 'content_hash' => $unit->content_hash, 'kind' => $job->kind, 'resource' => $result['resource'], 'accepted_by' => $user]);
                    app(AuditService::class)->record($user, 'import.unit.accepted', 'import_job', $job->id, ['reference' => $unit->reference, 'content_hash' => $unit->content_hash, 'resource' => $result['resource']]);
                    DB::commit();
                }
            } catch (\Throwable $error) {
                $errors = $this->domainErrors($error, $rows);
            } finally {
                if (DB::transactionLevel() > $level) {
                    DB::rollBack($level);
                }
            }
        }
        $unit->status = $errors ? 'invalid' : ($dryRun ? 'valid' : 'imported');
        $unit->errors = $errors;
        if ($errors) {
            $unit->resource = null;
            $unit->summary = [];
        }
        $unit->save();
    }

    private function validateUnits(ImportJob $job, array $groups, int $user): void
    {
        $duplicates = $this->repeatedIdentities($job->kind, $groups);
        foreach (ImportUnit::where('import_job_id', $job->id)->orderBy('position')->get() as $unit) {
            if ($unit->status === 'imported') {
                continue;
            }
            $rows = $groups[$unit->reference_hash];
            if (in_array($unit->reference_hash, $duplicates, true)) {
                $unit->fill(['status' => 'invalid', 'errors' => [ImportSchema::issue($rows[0]['row'], $job->kind === 'third-parties' ? 'rif' : 'unidad', 'IMPORT_DUPLICATE_IDENTITY', 'La misma identidad aparece en varias referencias del archivo. Conserva una sola unidad completa.')], 'summary' => [], 'resource' => null]);
                $unit->save();
                continue;
            }
            $this->check($job, $unit, $rows, $user, true);
        }
        $this->finish($job);
    }

    private function finish(ImportJob $job): void
    {
        $units = ImportUnit::where('import_job_id', $job->id)->orderBy('position')->get();
        $counts = ['total' => $units->count(), 'valid' => 0, 'invalid' => 0, 'imported' => 0, 'already_imported' => 0];
        foreach ($units as $unit) {
            ++$counts[$unit->status];
        }
        $accepted = $counts['imported'] + $counts['already_imported'];
        $job->status = $accepted === $counts['total'] ? 'complete' : ($accepted ? 'partial' : 'ready');
        $job->result = $counts;
        $job->preview_hash = $this->hash(['version' => $job->version, 'data_hash' => $job->data_hash, 'units' => $units->map(fn($u) => $u->only(['id', 'status', 'content_hash', 'errors', 'summary', 'resource']))->all()]);
        $job->save();
    }

    private function version(ImportJob $job, int $version, string $hash): void
    {
        if ($job->version !== $version || !hash_equals($job->preview_hash, $hash)) {
            throw new ApiProblem('IMPORT_PREVIEW_CHANGED', 'La validación cambió. Carga la versión actual y revisa sus resultados antes de confirmar.', 412);
        }
    }

    public function revalidate(string $id, int $version, string $hash, int $user): array
    {
        return DB::transaction(function () use ($id, $version, $hash, $user) {
            $this->lockBook();
            $job = ImportJob::whereKey($id)->lockForUpdate()->firstOrFail();
            $this->authorize($job->kind, $user);
            $this->version($job, $version, $hash);
            ++$job->version;
            $this->validateUnits($job, $this->dataset($job), $user);
            app(AuditService::class)->record($user, 'import.revalidated', 'import_job', $job->id, ['version' => $job->version, 'summary' => $job->result]);
            return $this->resource($job);
        });
    }

    public function apply(string $id, int $version, string $hash, array $selected, int $user): array
    {
        return DB::transaction(function () use ($id, $version, $hash, $selected, $user) {
            $this->lockBook();
            $job = ImportJob::whereKey($id)->lockForUpdate()->firstOrFail();
            $this->authorize($job->kind, $user);
            $this->version($job, $version, $hash);
            $units = ImportUnit::where('import_job_id', $id)->whereIn('id', $selected)->orderBy('position')->get();
            if ($units->count() !== count($selected) || $units->contains(fn($unit) => !in_array($unit->status, ['valid', 'imported', 'already_imported'], true))) {
                throw new ApiProblem('IMPORT_SELECTION', 'Selecciona únicamente unidades completas y válidas de esta validación.');
            }
            $groups = $this->dataset($job);
            foreach ($units as $unit) {
                if (in_array($unit->status, ['imported', 'already_imported'], true)) {
                    continue;
                }
                // Every selected unit is rechecked against current catalogs and
                // accepted references, under the same company lock as its write.
                $this->check($job, $unit, $groups[$unit->reference_hash], $user, false);
            }
            ++$job->version;
            $this->finish($job);
            app(AuditService::class)->record($user, 'import.confirmed', 'import_job', $job->id, ['version' => $job->version, 'selected' => $selected, 'summary' => $job->result]);
            return $this->resource($job);
        });
    }

    public function resource(ImportJob $job): array
    {
        return [...$job->only(['id', 'kind', 'filename', 'file_hash', 'status', 'version', 'options', 'preview_hash', 'created_at', 'updated_at']), 'summary' => $job->result, 'units' => ImportUnit::where('import_job_id', $job->id)->orderBy('position')->get()->map(fn($u) => $u->only(['id', 'reference', 'position', 'status', 'rows', 'errors', 'summary', 'resource']))->all()];
    }

    public function rows(ImportJob $job, ?string $unitId = null): array
    {
        $groups = $this->dataset($job);
        if ($unitId) {
            $unit = ImportUnit::where('import_job_id', $job->id)->findOrFail($unitId);
            return $groups[$unit->reference_hash];
        }
        return $groups;
    }
}
