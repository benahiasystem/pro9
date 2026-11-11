<?php

namespace Modules\Accounting\Services;

use App\Models\Tenant\User;
use Carbon\CarbonImmutable;
use Modules\Accounting\Domain\{ApiProblem, CompanyContext, Permissions};
use Modules\Accounting\Models\{Device, Account, ThirdParty, Period, JournalEntry, SourceDocument, ExchangeRate, TaxProfile, RuleSet};
use Modules\Accounting\Support\TenantDB as DB;
final class WorkspaceService
{
    private function bounded(string $model, string $label, int $limit = 5000): mixed
    {
        $rows = $model::query()->limit($limit + 1)->get();
        if ($rows->count() > $limit) {
            throw new ApiProblem('WORKSPACE_CATALOG_TOO_LARGE', 'El catálogo de ' . $label . ' supera el alcance de esta copia. Revisa la preparación antes de continuar.', 413);
        }
        return $rows;
    }
    public function prepare(User $user, array $input): array
    {
        return DB::transaction(function () use ($user, $input) {
            $context = app(CompanyContext::class);
            $company = $context->company();
            $maximum = min(365, max(1, $company->workspace_days));
            $end = CarbonImmutable::parse($input['to'] ?? $input['period'] . '-01', 'America/Caracas');
            if (empty($input['to'])) {
                $end = $end->endOfMonth();
            }
            $start = !empty($input['from']) ? CarbonImmutable::parse($input['from'], 'America/Caracas') : $end->subDays($maximum - 1);
            if ($start->isAfter($end) || $start->diffInDays($end) + 1 > $maximum) {
                throw new ApiProblem('WORKSPACE_DATE_RANGE', 'Selecciona un intervalo de hasta ' . $maximum . ' días, según la configuración de esta empresa.');
            }
            $from = $start->format('Y-m-d');
            $to = $end->format('Y-m-d');
            $device = Device::where('user_id', $user->id)->where('device_id', $input['device_id'])->lockForUpdate()->first();
            if ($device?->revoked_at) {
                throw new ApiProblem('DEVICE_REVOKED', 'Este equipo fue revocado. Solicita su habilitación al administrador.', 403);
            }
            $until = now()->addHours(min(72, max(1, $company->offline_hours)));
            $device ??= new Device(['user_id' => $user->id, 'device_id' => $input['device_id']]);
            $device->fill(['name' => $input['name'], 'authorized_until' => $until, 'last_seen_at' => now()])->save();
            $entries = JournalEntry::whereBetween('accounting_date', [$from, $to])->orderBy('accounting_date')->limit(2001)->get();
            $documents = SourceDocument::whereBetween('fiscal_date', [$from, $to])->orderBy('fiscal_date')->limit(2001)->get();
            if ($entries->count() > 2000 || $documents->count() > 2000) {
                throw new ApiProblem('WORKSPACE_TOO_LARGE', 'El intervalo supera los 2.000 comprobantes o documentos de la copia. Reduce sus fechas antes de prepararla.', 413);
            }
            $membership = app(Permissions::class)->membership($user, $context->id());
            $package = ['schema' => 1, 'device_id' => $input['device_id'], 'issued_at' => now()->toIso8601String(), 'expires_at' => $until->toIso8601String(), 'period' => $input['period'], 'scope' => ['from' => $from, 'to' => $to, 'maximum_days' => $maximum, 'entries_limit' => 2000, 'documents_limit' => 2000, 'complete_within_scope' => true], 'company' => [...$company->toArray(), 'role' => $membership->role, 'roles' => app(Permissions::class)->roles($membership), 'permissions' => app(Permissions::class)->forMembership($membership)], 'user' => $user->only(['id', 'name', 'email']), 'accounts' => $this->bounded(Account::class, 'cuentas'), 'parties' => $this->bounded(ThirdParty::class, 'terceros'), 'periods' => $this->bounded(Period::class, 'períodos'), 'entries' => $entries->map(fn($entry) => app(EntryService::class)->resource($entry))->all(), 'documents' => $documents->map(fn($document) => app(DocumentService::class)->resource($document))->all(), 'tax_configuration' => ['profiles' => $this->bounded(TaxProfile::class, 'perfiles fiscales'), 'rules' => $this->bounded(RuleSet::class, 'reglas fiscales')], 'exchange_rates' => $this->bounded(ExchangeRate::class, 'tasas de cambio'), 'reports' => [], 'report_snapshots' => [], 'report_ranges' => [], 'demo' => (bool) config('accounting.demo')];
            $ranges = [[$from, $to]];
            for ($month = $start->startOfMonth(); !$month->isAfter($end); $month = $month->addMonth()) {
                $monthFrom = max($from, $month->format('Y-m-d'));
                $monthTo = min($to, $month->endOfMonth()->format('Y-m-d'));
                if ([$monthFrom, $monthTo] !== [$from, $to]) {
                    $ranges[] = [$monthFrom, $monthTo];
                }
            }
            foreach ($ranges as [$reportFrom, $reportTo]) {
                $package['report_ranges'][] = ['from' => $reportFrom, 'to' => $reportTo];
                foreach (['dashboard', 'trial-balance', 'balance-sheet', 'income-statement'] as $kind) {
                    $report = app(ReportService::class)->build($kind, ['from' => $reportFrom, 'to' => $reportTo]);
                    $package['report_snapshots'][$kind . '|' . $reportFrom . '|' . $reportTo] = $report;
                    if ($reportFrom === $input['period'] . '-01' && $reportTo === CarbonImmutable::parse($reportFrom)->endOfMonth()->format('Y-m-d')) {
                        $package['reports'][$kind] = $report;
                    }
                }
            }
            if (strlen(json_encode($package, JSON_THROW_ON_ERROR)) > (int) config('accounting.workspace_max_bytes', 104857600)) {
                throw new ApiProblem('WORKSPACE_TOO_LARGE', 'La copia supera el tamaño permitido. Reduce sus fechas antes de prepararla.', 413);
            }
            app(AuditService::class)->record($user->id, 'offline.prepared', 'device', $device->id, ['scope' => $package['scope'], 'entries' => $entries->count(), 'documents' => $documents->count(), 'authorized_until' => $until->toIso8601String()]);
            return $package;
        }, 5);
    }
}
