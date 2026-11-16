<?php

namespace Modules\Accounting\Services;

use Carbon\CarbonImmutable;
use Modules\Accounting\Domain\{ApiProblem, CompanyContext, Exact};
use Modules\Accounting\Models\{TaxProfile, RuleSet, SourceDocument};
use Modules\Accounting\Support\TenantDB as DB;
final class TaxConfigurationService
{
    public function profileAt(string $date): TaxProfile
    {
        return TaxProfile::where('effective_from', '<=', $date)->where(fn($query) => $query->whereNull('effective_until')->orWhere('effective_until', '>=', $date))->orderByDesc('effective_from')->first() ?? throw new ApiProblem('TAX_PROFILE_REQUIRED', 'El contador debe confirmar un perfil fiscal con vigencia para la fecha del documento.');
    }
    public function addProfile(array $input, int $user): TaxProfile
    {
        return DB::transaction(function () use ($input, $user) {
            DB::table('accounting_companies')->where('id', app(CompanyContext::class)->id())->lockForUpdate()->first();
            $from = $input['effective_from'];
            if (!empty($input['effective_until']) && $input['effective_until'] < $from) {
                throw new ApiProblem('INVALID_EFFECTIVE_RANGE', 'La vigencia final no puede ser anterior a la inicial.');
            }
            if (TaxProfile::where('effective_from', '>=', $from)->exists()) {
                throw new ApiProblem('PROFILE_DATE_ORDER', 'La nueva vigencia debe comenzar después de la última configuración registrada.');
            }
            if (SourceDocument::where('fiscal_date', '>=', $from)->whereIn('status', ['validated', 'proposed', 'posted'])->exists()) {
                throw new ApiProblem('HISTORICAL_PROFILE_IN_USE', 'Existen documentos validados en ese intervalo. Conserva su perfil histórico y configura una vigencia posterior.');
            }
            $details = $input['details'];
            if ($input['profile'] !== 'ordinary' && ($details['input_tax_treatment'] ?? '') === 'deductible') {
                throw new ApiProblem('PROFILE_TREATMENT_CONFLICT', 'La recuperación de IVA de compras requiere el perfil ordinario revisado por el contador.');
            }
            if (($details['spe'] ?? false) && empty($details['notification_reference'])) {
                throw new ApiProblem('SPE_NOTIFICATION_REQUIRED', 'Registra la notificación y su referencia. Los importes no asignan la condición SPE automáticamente.');
            }
            $previous = TaxProfile::orderByDesc('effective_from')->lockForUpdate()->first();
            if ($previous && (!$previous->effective_until || $previous->effective_until->format('Y-m-d') >= $from)) {
                $previous->effective_until = CarbonImmutable::parse($from)->subDay()->format('Y-m-d');
                $previous->save();
            }
            $profile = TaxProfile::create([...$input, 'approved_by' => $user, 'version' => (int) TaxProfile::max('version') + 1]);
            app(AuditService::class)->record($user, 'tax.profile_configured', 'tax_profile', $profile->id, ['profile' => $profile->toArray(), 'previous_profile_id' => $previous?->id]);
            return $profile;
        }, 5);
    }
    public function createRule(array $input, int $user): RuleSet
    {
        return DB::transaction(function () use ($input, $user) {
            DB::table('accounting_companies')->where('id', app(CompanyContext::class)->id())->lockForUpdate()->first();
            if (!empty($input['effective_until']) && $input['effective_until'] < $input['effective_from']) {
                throw new ApiProblem('INVALID_EFFECTIVE_RANGE', 'Revisa el intervalo de vigencia de la regla.');
            }
            if (isset($input['rate_percent'])) {
                $rate = Exact::of($input['rate_percent']);
                if ($rate->isNegative() || $rate->isGreaterThan('100')) {
                    throw new ApiProblem('INVALID_TAX_RATE', 'El porcentaje debe estar entre cero y cien.');
                }
                $input['rate_percent'] = (string) $rate;
            }
            $rule = RuleSet::create([...$input, 'version' => (int) RuleSet::where('code', $input['code'])->max('version') + 1, 'status' => 'draft']);
            app(AuditService::class)->record($user, 'tax.rule_drafted', 'rule_set', $rule->id, ['rule' => $rule->toArray()]);
            return $rule;
        }, 5);
    }
    public function approveRule(string $id, int $version, int $user): RuleSet
    {
        return DB::transaction(function () use ($id, $version, $user) {
            $rule = RuleSet::whereKey($id)->lockForUpdate()->firstOrFail();
            if ($rule->version !== $version) {
                throw new ApiProblem('VERSION_CONFLICT', 'La regla cambió. Revisa su versión actual.', 412);
            }
            if ($rule->status !== 'draft') {
                throw new ApiProblem('IMMUTABLE_RULE', 'La regla aprobada conserva su versión. Registra otra versión para modificarla.');
            }
            if ($rule->tax === 'IGTF' && $rule->rate_percent !== null && Exact::of($rule->rate_percent)->isZero() && (empty($rule->conditions['payment_currency']) || empty($rule->conditions['scope']))) {
                throw new ApiProblem('CONDITIONAL_IGTF_REQUIRED', 'Una regla de IGTF al cero por ciento necesita el supuesto y la moneda de pago expresamente delimitados.');
            }
            $rule->status = 'approved';
            $rule->approved_by = $user;
            $rule->approved_at = now();
            $rule->save();
            app(AuditService::class)->record($user, 'tax.rule_approved', 'rule_set', $id, ['version' => $rule->version, 'effective_from' => $rule->effective_from->format('Y-m-d'), 'source_reference' => $rule->source_reference]);
            return $rule;
        }, 5);
    }
    public function ruleFor(string $id, string $tax, string $date, array $context): RuleSet
    {
        $rule = RuleSet::findOrFail($id);
        if ($rule->tax !== $tax || $rule->status !== 'approved' || $rule->effective_from->format('Y-m-d') > $date || $rule->effective_until && $rule->effective_until->format('Y-m-d') < $date) {
            throw new ApiProblem('RULE_NOT_EFFECTIVE', 'La regla seleccionada no está aprobada o no corresponde a la fecha y al impuesto.');
        }
        foreach ($rule->conditions ?? [] as $key => $expected) {
            $allowed = is_array($expected) ? $expected : [$expected];
            if (!array_key_exists($key, $context) || !in_array($context[$key], $allowed, true)) {
                throw new ApiProblem('RULE_CONDITION_MISMATCH', 'El documento no cumple las condiciones de la regla seleccionada.');
            }
        }
        return $rule;
    }
}
