<?php

namespace Modules\Accounting\Services;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Modules\Accounting\Domain\{ApiProblem, CompanyContext, Exact};
use Modules\Accounting\Models\{SourceDocument, SourceVersion, ThirdParty, FiscalRecord, JournalEntry, Account, AccountMapping};
use Modules\Accounting\Support\TenantDB as DB;
final class DocumentService
{
    private const AMOUNTS = ['base', 'vat', 'other_taxes', 'total'];
    public function identity(string $kind, string $number, string $partyTaxId, ?string $control, string $date): ?string
    {
        if (trim($number) === '' || $partyTaxId === '') {
            return null;
        }
        $issuer = str_starts_with($kind, 'sale_') ? app(CompanyContext::class)->company()->tax_id : $partyTaxId;
        $canonical = fn(string $value) => preg_replace('/\s+/', '', mb_strtoupper(trim($value)));
        $number = preg_replace_callback('/[0-9]+$/', fn($match) => ltrim($match[0], '0') ?: '0', $canonical($number));
        $issuer = preg_replace('/[^A-Z0-9]/', '', $canonical($issuer));
        return hash('sha256', $issuer . '|' . $kind . '|' . $number . '|' . ($control ? preg_replace('/[^A-Z0-9]/', '', $canonical($control)) : substr($date, 0, 4)));
    }
    private function fields(array $input): array
    {
        $amounts = [];
        foreach (self::AMOUNTS as $key) {
            $amounts[$key] = (string) Exact::of($input['amounts'][$key] ?? '0', 12);
            if (BigDecimal::of($amounts[$key])->isNegative()) {
                throw new ApiProblem('NEGATIVE_DOCUMENT_AMOUNT', 'Los importes del documento se registran positivos; el tipo de nota determina su efecto.');
            }
        }
        $party = !empty($input['third_party_id']) ? ThirdParty::findOrFail($input['third_party_id']) : null;
        if (!empty($input['related_document_id'])) {
            SourceDocument::findOrFail($input['related_document_id']);
        }
        $key = $this->identity($input['kind'], $input['number'] ?? '', $party?->tax_id ?? '', $input['fiscal_control'] ?? null, $input['issue_date']);
        return ['kind' => $input['kind'], 'number' => $input['number'] ?? '', 'fiscal_control' => $input['fiscal_control'] ?? null, 'issue_date' => $input['issue_date'], 'fiscal_date' => $input['fiscal_date'], 'received_on' => $input['received_on'] ?? null, 'due_date' => $input['due_date'] ?? null, 'third_party_id' => $party?->id, 'related_document_id' => $input['related_document_id'] ?? null, 'currency' => $input['currency'], 'exchange_rate' => (string) Exact::of($input['exchange_rate'], 12), 'exchange_source' => $input['exchange_source'] ?? '', 'exchange_date' => $input['exchange_date'], 'rate_evidence_reference' => $input['rate_evidence_reference'] ?? null, 'amounts' => $amounts, 'tax_components' => $input['tax_components'] ?? [], 'natural_key' => $key];
    }
    public function ensureIdentity(?string $key, ?string $except = null): void
    {
        $candidate = $this->findIdentity($key, $except);
        if ($candidate) {
            throw new ApiProblem('POSSIBLE_DUPLICATE_DOCUMENT', 'Existe un documento con esta identidad. Revisa su procedencia y vincula el origen antes de crear otro registro.', 409, ['document_id' => $candidate->id, 'number' => $candidate->number, 'origin' => $candidate->origin]);
        }
    }
    public function findIdentity(?string $key, ?string $except = null): ?SourceDocument
    {
        if (!$key) {
            return null;
        }
        $candidate = SourceDocument::where('natural_key', $key)->when($except, fn($q) => $q->where('id', '!=', $except))->first();
        if (!$candidate) {
            foreach (SourceDocument::whereNull('natural_key')->whereNotNull('third_party_id')->where('number', '!=', '')->when($except, fn($q) => $q->where('id', '!=', $except))->get() as $legacy) {
                $party = ThirdParty::find($legacy->third_party_id);
                $identity = $this->identity($legacy->kind, $legacy->number, $legacy->metadata['party_snapshot']['tax_id'] ?? $party?->tax_id ?? '', $legacy->fiscal_control, $legacy->issue_date->format('Y-m-d'));
                if ($identity === $key) {
                    $candidate = $legacy;
                    break;
                }
            }
        }
        return $candidate;
    }
    private function evidence(SourceDocument $document, array $snapshot, int $user): void
    {
        $snapshot['party_snapshot'] = $document->third_party_id ? ThirdParty::find($document->third_party_id)?->only(['id', 'name', 'tax_id']) : null;
        $hash = hash('sha256', json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        app(SourceHistoryService::class)->append($document, $snapshot, $hash, $user, $document->source_revision);
    }
    public function create(array $input, int $user): SourceDocument
    {
        return DB::transaction(function () use ($input, $user) {
            DB::table('accounting_companies')->where('id', app(CompanyContext::class)->id())->lockForUpdate()->first();
            $data = $this->fields($input);
            $this->ensureIdentity($data['natural_key']);
            $document = SourceDocument::create([...$data, 'origin' => 'manual', 'status' => 'draft', 'created_by' => $user, 'fiscal_amounts' => [], 'metadata' => ['rate_reason' => $input['rate_reason'] ?? '', 'tax_explanation' => $input['tax_explanation'] ?? '', 'tax_context' => $input['tax_context'] ?? []]]);
            $this->evidence($document, $document->toArray(), $user);
            app(AuditService::class)->record($user, 'document.created', 'source_document', $document->id, ['version' => $document->version, 'source_revision' => $document->source_revision]);
            return $document;
        }, 5);
    }
    public function version(SourceDocument $document, int $version): void
    {
        if ($document->version !== $version) {
            throw new ApiProblem('VERSION_CONFLICT', 'El documento cambió. Conserva tu trabajo y revisa la versión actual.', 412, ['current' => $this->resource($document)]);
        }
    }
    public function edit(string $id, array $input, int $version, int $user): SourceDocument
    {
        return DB::transaction(function () use ($id, $input, $version, $user) {
            DB::table('accounting_companies')->where('id', app(CompanyContext::class)->id())->lockForUpdate()->first();
            $document = SourceDocument::whereKey($id)->lockForUpdate()->firstOrFail();
            $this->version($document, $version);
            if (\Modules\Accounting\Models\OpenItem::where('source_document_id', $id)->where('is_opening', true)->where('status', '!=', 'reversed')->exists()) {
                throw new ApiProblem('OPENING_SOURCE_IMMUTABLE', 'Este documento respalda una partida inicial contabilizada. Conserva su identidad y revisa su corrección mediante la apertura y sus auxiliares.', 409);
            }
            if ($document->origin !== 'manual') {
                throw new ApiProblem('SOURCE_READ_ONLY', 'La información original se corrige en SAB. Su clasificación fiscal se revisa al validar.');
            }
            if (JournalEntry::where('source_document_id', $id)->whereNotNull('posted_at')->exists()) {
                throw new ApiProblem('POSTED_DOCUMENT_IMMUTABLE', 'Conserva el documento contabilizado. Registra una nota o un reverso para su corrección.', 409);
            }
            $data = $this->fields($input);
            $this->ensureIdentity($data['natural_key'], $id);
            $document->fill([...$data, 'status' => 'draft', 'source_revision' => $document->source_revision + 1, 'version' => $document->version + 1, 'validated_by' => null, 'validated_at' => null, 'validated_version' => null, 'metadata' => [...$document->metadata ?? [], 'rate_reason' => $input['rate_reason'] ?? '', 'tax_explanation' => $input['tax_explanation'] ?? '', 'tax_context' => $input['tax_context'] ?? [], 'validation' => null], 'fiscal_amounts' => []]);
            $document->save();
            $this->evidence($document, $document->toArray(), $user);
            FiscalRecord::where('source_document_id', $id)->whereIn('status', ['validated', 'source_changed'])->update(['status' => 'superseded']);
            foreach (JournalEntry::where('source_document_id', $id)->whereNull('posted_at')->lockForUpdate()->get() as $entry) {
                $entry->fill(['status' => 'observed', 'approved_by' => null, 'approved_at' => null, 'approved_version' => null, 'version' => $entry->version + 1]);
                $entry->save();
            }
            app(AuditService::class)->record($user, 'document.updated', 'source_document', $id, ['version' => $document->version, 'source_revision' => $document->source_revision, 'prior_approval_invalidated' => true]);
            return $document;
        }, 5);
    }
    private function guardAntecedent(SourceDocument $document): void
    {
        $cutoff = app(CompanyContext::class)->company()->migration_date;
        if ($document->status === 'opening_antecedent' || $cutoff && $document->issue_date->format('Y-m-d') < substr((string) $cutoff, 0, 10) || \Modules\Accounting\Models\OpenItem::where('source_document_id', $document->id)->where('is_opening', true)->exists()) {
            throw new ApiProblem('OPENING_ANTECEDENT', 'El documento anterior al inicio se enlaza como antecedente de la apertura; no genera otra venta o compra.');
        }
    }
    public function validate(string $id, int $version, array $review, int $user): SourceDocument
    {
        return DB::transaction(function () use ($id, $version, $review, $user) {
            DB::table('accounting_companies')->where('id', app(CompanyContext::class)->id())->lockForUpdate()->first();
            $document = SourceDocument::whereKey($id)->lockForUpdate()->firstOrFail();
            $this->version($document, $version);
            if (in_array($document->status, ['posted', 'excluded'], true)) {
                throw new ApiProblem('DOCUMENT_STATE', 'El documento no está pendiente de validación.');
            }
            app(SourceHistoryService::class)->requireCurrent($document);
            app(Pro9SourceReader::class)->assertCurrent($document);
            if (JournalEntry::where('source_document_id', $id)->whereNotNull('posted_at')->whereDoesntHave('reversal', fn($q) => $q->whereNotNull('posted_at'))->exists()) {
                throw new ApiProblem('POSTED_SOURCE_CHANGE', 'Resuelve primero la corrección del comprobante contabilizado y sus auxiliares.', 409);
            }
            $classification = array_intersect_key($review, array_flip(['fiscal_date', 'received_on', 'related_document_id', 'exchange_rate', 'exchange_date', 'exchange_source', 'rate_evidence_reference']));
            if (isset($classification['exchange_rate'])) {
                $classification['exchange_rate'] = (string) Exact::of($classification['exchange_rate'], 12);
            }
            if (array_key_exists('exchange_source', $classification)) {
                $classification['exchange_source'] ??= '';
            }
            $document->fill($classification);
            if (!$document->third_party_id || !ThirdParty::whereKey($document->third_party_id)->where('active', true)->exists() || trim($document->number) === '') {
                throw new ApiProblem('DOCUMENT_IDENTIFICATION_REQUIRED', 'Completa el tercero activo y la identificación del documento.');
            }
            $date = $document->fiscal_date->format('Y-m-d');
            if ($date > now('America/Caracas')->format('Y-m-d')) {
                throw new ApiProblem('FUTURE_FISCAL_EVENT', 'Conserva como borrador el documento cuyo hecho fiscal todavía no ha ocurrido.');
            }
            if ($document->origin === 'pro9' && empty($review['source_confirmed'])) {
                throw new ApiProblem('SOURCE_REVIEW_REQUIRED', 'Confirma el carácter definitivo y el estado de origen mostrado por SAB.');
            }
            if (str_contains($document->kind, '_note')) {
                $related = $document->related_document_id ? SourceDocument::find($document->related_document_id) : null;
                $invoiceKind = str_starts_with($document->kind, 'sale_') ? 'sale_invoice' : 'purchase_invoice';
                if (!$related || $related->id === $document->id || $related->kind !== $invoiceKind || $related->third_party_id !== $document->third_party_id || $related->currency !== $document->currency) {
                    throw new ApiProblem('RELATED_DOCUMENT_REQUIRED', 'La nota requiere la factura afectada del mismo tipo de operación, tercero y moneda.');
                }
            }
            $profile = app(TaxConfigurationService::class)->profileAt($date);
            $amounts = $document->amounts;
            $base = BigDecimal::of($amounts['base']);
            $vat = BigDecimal::of($amounts['vat']);
            $other = BigDecimal::of($amounts['other_taxes'] ?? (string) BigDecimal::of($amounts['taxes'] ?? $amounts['vat'])->minus($vat));
            $total = BigDecimal::of($amounts['total']);
            if (!$total->isPositive() || $base->isNegative() || $vat->isNegative() || $other->isNegative() || !$base->plus($vat)->plus($other)->toScale(2, RoundingMode::HALF_UP)->isEqualTo($total->toScale(2, RoundingMode::HALF_UP))) {
                throw new ApiProblem('DOCUMENT_TOTAL_MISMATCH', 'La base, el impuesto y los otros tributos no concilian con el total del documento.');
            }
            $rate = Exact::of($document->exchange_rate, 12);
            if (!$rate->isPositive() || $document->currency === 'VES' && !$rate->isEqualTo('1')) {
                throw new ApiProblem('DOCUMENT_RATE_REQUIRED', 'El libro VES requiere tasa uno para VES y una tasa histórica positiva para divisas.');
            }
            if ($document->currency !== 'VES' && (trim($document->exchange_source) === '' || empty($document->rate_evidence_reference) || empty($review['rate_reason'] ?? $document->metadata['rate_reason'] ?? ''))) {
                throw new ApiProblem('RATE_EVIDENCE_REQUIRED', 'Conserva la fuente, la evidencia y el motivo de la tasa histórica aplicada.');
            }
            $components = $review['tax_components'] ?? $document->tax_components ?? [];
            $vatSum = BigDecimal::zero();
            $vatBases = BigDecimal::zero();
            $otherSum = BigDecimal::zero();
            $rules = [];
            foreach ($components as $component) {
                if (empty($component['rule_id'])) {
                    throw new ApiProblem('TAX_RULE_REQUIRED', 'Selecciona la regla fiscal revisada para cada impuesto.');
                }
                $tax = $component['tax'];
                $context = [...$review['tax_context'] ?? $document->metadata['tax_context'] ?? [], 'currency' => $document->currency];
                $rule = app(TaxConfigurationService::class)->ruleFor($component['rule_id'], $tax, $date, $context);
                $taxBase = Exact::of($component['base'], 12);
                $amount = Exact::of($component['amount'], 12);
                if ($taxBase->isNegative() || $amount->isNegative()) {
                    throw new ApiProblem('INVALID_TAX_COMPONENT', 'Las bases y los impuestos se registran positivos.');
                }
                if ($tax === 'IVA') {
                    if ($rule->rate_percent === null || !Exact::of((string) $taxBase->multipliedBy($rule->rate_percent)->dividedBy('100')->toScale(2, RoundingMode::HALF_UP))->isEqualTo($amount->toScale(2, RoundingMode::HALF_UP))) {
                        throw new ApiProblem('TAX_COMPONENT_MISMATCH', 'El IVA no coincide con la base y la regla seleccionada.');
                    }
                    $vatSum = $vatSum->plus($amount);
                    $vatBases = $vatBases->plus($taxBase);
                } else {
                    if (empty($component['manual_reason'])) {
                        throw new ApiProblem('MANUAL_TAX_DETERMINATION_REQUIRED', 'Este supuesto se registra con la determinación documentada del contador. No tiene cálculo automático implementado.');
                    }
                    $otherSum = $otherSum->plus($amount);
                }
                $rules[] = ['rule' => $rule->toArray(), 'component' => $component, 'calculation_mode' => $tax === 'IVA' ? 'reviewed_percentage' : 'manual_determination'];
            }
            if ($vatBases->isGreaterThan($base)) {
                throw new ApiProblem('TAX_BASE_MISMATCH', 'Las bases clasificadas de IVA superan la base del documento. Revisa las alícuotas y evita repetir la misma base.');
            }
            if (!$vatSum->toScale(2, RoundingMode::HALF_UP)->isEqualTo($vat->toScale(2, RoundingMode::HALF_UP)) || !$otherSum->toScale(2, RoundingMode::HALF_UP)->isEqualTo($other->toScale(2, RoundingMode::HALF_UP))) {
                throw new ApiProblem('TAX_CLASSIFICATION_REQUIRED', 'Clasifica los importes de IVA y otros impuestos con reglas vigentes y sus soportes.');
            }
            if ($vat->isZero() && $profile->profile === 'ordinary' && empty($review['tax_explanation'] ?? $document->metadata['tax_explanation'] ?? '')) {
                throw new ApiProblem('ZERO_TAX_REASON_REQUIRED', 'Documenta el tratamiento de la operación sin IVA. Un importe cero no acredita exención.');
            }
            $party = ThirdParty::findOrFail($document->third_party_id);
            $key = $this->identity($document->kind, $document->number, $party->tax_id, $document->fiscal_control, $document->issue_date->format('Y-m-d'));
            $this->ensureIdentity($key, $id);
            $fiscal = [];
            foreach (['base' => $base, 'vat' => $vat, 'other_taxes' => $other, 'total' => $total] as $name => $value) {
                $fiscal[$name] = (string) $value->multipliedBy($rate)->toScale(2, RoundingMode::HALF_UP);
            }
            $validation = ['profile' => $profile->toArray(), 'rules' => $rules, 'reviewer_id' => $user, 'reviewed_at' => now()->toIso8601String(), 'source_revision' => $document->source_revision, 'tax_context' => $review['tax_context'] ?? $document->metadata['tax_context'] ?? [], 'source_confirmed' => $document->origin === 'manual' || !empty($review['source_confirmed']), 'tax_explanation' => $review['tax_explanation'] ?? $document->metadata['tax_explanation'] ?? '', 'rate' => ['value' => $document->exchange_rate, 'source' => $document->exchange_source, 'date' => $document->exchange_date, 'evidence' => $document->rate_evidence_reference, 'reason' => $review['rate_reason'] ?? $document->metadata['rate_reason'] ?? '']];
            $validation['document'] = ['id' => $document->id, 'kind' => $document->kind, 'number' => $document->number, 'fiscal_control' => $document->fiscal_control, 'issue_date' => $document->issue_date->format('Y-m-d'), 'currency' => $document->currency, 'original_amounts' => $document->amounts, 'related_document_id' => $document->related_document_id, 'party' => $party->only(['id', 'name', 'tax_id']), 'issuer' => app(CompanyContext::class)->company()->only(['name', 'tax_id'])];
            $validation['support_manifest'] = app(EvidenceManifest::class)->document($document->id);
            $isOpeningAntecedent = \Modules\Accounting\Models\OpenItem::where('source_document_id', $id)->where('is_opening', true)->exists();
            $document->fill(['status' => $isOpeningAntecedent ? 'opening_antecedent' : 'validated', 'version' => $document->version + 1, 'validated_version' => $document->version + 1, 'validated_by' => $user, 'validated_at' => now(), 'natural_key' => $key, 'fiscal_amounts' => $fiscal, 'tax_components' => $components, 'issues' => [], 'metadata' => [...$document->metadata ?? [], 'validation' => $validation, 'party_snapshot' => $party->only(['id', 'tax_id', 'name']), 'issuer_snapshot' => app(CompanyContext::class)->company()->only(['name', 'tax_id'])]]);
            $document->save();
            $fiscalVersion = (int) FiscalRecord::where('source_document_id', $id)->max('version') + 1;
            FiscalRecord::where('source_document_id', $id)->whereIn('status', ['validated', 'source_changed'])->update(['status' => 'superseded']);
            FiscalRecord::create(['source_document_id' => $id, 'kind' => $document->kind, 'event_date' => $date, 'fiscal_period' => substr($date, 0, 7), 'profile' => $profile->profile, 'amounts_ves' => $fiscal, 'tax_components' => $components, 'version' => $fiscalVersion, 'status' => 'validated', 'rule_snapshot' => $validation]);
            foreach (JournalEntry::where('source_document_id', $id)->whereNull('posted_at')->lockForUpdate()->get() as $entry) {
                $entry->fill(['status' => 'observed', 'approved_by' => null, 'approved_at' => null, 'approved_version' => null, 'approval_reason' => null, 'version' => $entry->version + 1]);
                $entry->save();
            }
            app(AuditService::class)->record($user, 'document.validated', 'source_document', $id, ['version' => $document->version, 'validation' => $validation, 'fiscal_amounts' => $fiscal]);
            return $document;
        }, 5);
    }
    public function propose(string $id, int $version, string $date, array $selected, int $user, array $options = []): JournalEntry
    {
        return DB::transaction(function () use ($id, $version, $date, $selected, $user, $options) {
            $document = SourceDocument::whereKey($id)->lockForUpdate()->firstOrFail();
            $this->version($document, $version);
            $this->guardAntecedent($document);
            if (!in_array($document->status, ['validated', 'proposed'], true) || empty($document->metadata['validation'])) {
                throw new ApiProblem('DOCUMENT_VALIDATION_REQUIRED', 'Valida el documento y su configuración fiscal antes de preparar el asiento.');
            }
            if (!in_array($document->kind, ['sale_invoice', 'purchase_invoice'], true)) {
                throw new ApiProblem('NOTE_APPLICATION_REQUIRED', 'La nota debe prepararse con su aplicación al documento y a sus partidas abiertas.');
            }
            $existing = JournalEntry::where('source_document_id', $id)->where('source_revision', $document->source_revision)->where('effect_type', 'document')->lockForUpdate()->first();
            if ($existing && $existing->rule_snapshot == $document->metadata['validation']) {
                return $existing;
            }
            if ($existing && (empty($options['replace_existing']) || mb_strlen(trim($options['reason'] ?? '')) < 10)) {
                throw new ApiProblem('PROPOSAL_REPLACEMENT_REQUIRED', 'Hay una propuesta con otra validación. Confirma su regeneración y documenta el motivo para conservar ambas evidencias.', 409, ['entry_id' => $existing->id, 'entry_version' => $existing->version]);
            }
            if (JournalEntry::where('source_document_id', $id)->whereNotNull('posted_at')->exists()) {
                app(SourceIncidentService::class)->requireReplacementPermit($document);
            }
            $purchase = $document->kind === 'purchase_invoice';
            $side = $purchase ? 'purchase' : 'sale';
            $mapping = AccountMapping::get()->keyBy('key');
            $account = function (string $role) use ($selected, $mapping, $side) {
                $id = $selected[$role] ?? $mapping[$side . '.' . $role]->account_id ?? $mapping[$role]->account_id ?? null;
                $account = $id ? Account::find($id) : null;
                if (!$account || !$account->active || !$account->posting) {
                    throw new ApiProblem('ACCOUNT_MAPPING_REQUIRED', 'Selecciona una cuenta activa de movimiento para ' . $role . '.', 422, ['mapping' => $role]);
                }
                return $account;
            };
            $counter = $account('counterparty');
            if ($counter->auxiliary !== ($purchase ? 'payable' : 'receivable')) {
                throw new ApiProblem('AUXILIARY_ACCOUNT_REQUIRED', 'La contrapartida de la factura debe usar el auxiliar de clientes o proveedores correspondiente.');
            }
            $lines = [];
            $fiscal = $document->fiscal_amounts;
            $amounts = $document->amounts;
            $line = function (Account $account, string $amount, string $original, bool $debit, string $reason) use ($document) {
                return ['account_id' => $account->id, 'third_party_id' => $document->third_party_id, 'document_reference' => $document->number, 'debit' => $debit ? $amount : '0.00', 'credit' => $debit ? '0.00' : $amount, 'original_currency' => $document->currency, 'original_amount' => (string) BigDecimal::of($original)->toScale(12, RoundingMode::HALF_UP), 'exchange_rate' => $document->exchange_rate, 'description' => $reason];
            };
            $lines[] = $line($counter, $fiscal['total'], $amounts['total'], !$purchase, $purchase ? 'Cuenta por pagar por el total de la factura.' : 'Cuenta por cobrar por el total de la factura.');
            $net = BigDecimal::of($fiscal['base']);
            $originalNet = BigDecimal::of($amounts['base']);
            $deductible = ($document->metadata['validation']['profile']['details']['input_tax_treatment'] ?? 'cost') === 'deductible';
            if ($purchase && !$deductible) {
                $net = $net->plus($fiscal['vat']);
                $originalNet = $originalNet->plus($amounts['vat']);
            }
            if (!$net->isZero()) {
                $lines[] = $line($account('base'), (string) $net, (string) $originalNet, $purchase, $purchase && !$deductible ? 'Compra e IVA incorporado al costo según el perfil revisado.' : ($purchase ? 'Base de la compra.' : 'Ingreso por la operación, sin volver a reconocerlo al cobrar.'));
            }
            if (BigDecimal::of($fiscal['vat'])->isPositive() && (!$purchase || $deductible)) {
                $lines[] = $line($account('vat'), $fiscal['vat'], $amounts['vat'], $purchase, 'IVA según las reglas y el perfil capturados en la validación.');
            }
            if (BigDecimal::of($fiscal['other_taxes'])->isPositive()) {
                $lines[] = $line($account('other_taxes'), $fiscal['other_taxes'], $amounts['other_taxes'] ?? (string) BigDecimal::of($amounts['taxes'])->minus($amounts['vat']), $purchase, 'Otros tributos con determinación documentada.');
            }
            $debit = BigDecimal::of(Exact::total(array_column($lines, 'debit')));
            $credit = BigDecimal::of(Exact::total(array_column($lines, 'credit')));
            $difference = $debit->minus($credit);
            if (!$difference->isZero()) {
                $rounding = $account('rounding');
                $lines[] = ['account_id' => $rounding->id, 'third_party_id' => $document->third_party_id, 'document_reference' => $document->number, 'debit' => $difference->isNegative() ? (string) $difference->abs() : '0.00', 'credit' => $difference->isPositive() ? (string) $difference : '0.00', 'original_currency' => 'VES', 'description' => 'Diferencia explícita de redondeo al convertir a VES.'];
            }
            $data = ['accounting_date' => $date, 'concept' => ($purchase ? 'Compra ' : 'Venta ') . $document->number, 'source_document_id' => $id, 'source_revision' => $document->source_revision, 'lines' => $lines, 'rule_snapshot' => $document->metadata['validation'], 'metadata' => ['template' => $side . '_invoice', 'document_version' => $document->version, 'rounding_difference' => (string) $difference]];
            if ($existing) {
                app(AuditService::class)->record($user, 'entry.proposal_regenerated', 'journal_entry', $existing->id, ['reason' => $options['reason'], 'prior_entry' => app(EntryService::class)->resource($existing)]);
                $entry = app(EntryService::class)->edit($existing->id, $data, $existing->version, $user);
                $entry->rule_snapshot = $data['rule_snapshot'];
                $entry->metadata = [...$data['metadata'], 'prepared_by' => $user];
                $entry->save();
            } else {
                $entry = app(EntryService::class)->create($data, $user);
            }
            $document->status = 'proposed';
            $document->version++;
            $document->save();
            app(AuditService::class)->record($user, 'document.proposed', 'source_document', $id, ['journal_entry_id' => $entry->id, 'source_revision' => $document->source_revision, 'accounts' => array_column($lines, 'account_id')]);
            return $entry;
        }, 5);
    }
    public function resource(SourceDocument $document): array
    {
        $incident = $document->origin === 'pro9' ? \Modules\Accounting\Models\Incident::where('kind', 'source_changed')->where('resource_type', 'source_document')->where('resource_id', $document->id)->where('status', 'open')->orderByRaw("CAST(JSON_UNQUOTE(JSON_EXTRACT(details, '$.revision')) AS UNSIGNED) DESC")->first() : null;
        return [...$document->toArray(), 'party' => $document->metadata['party_snapshot'] ?? ($document->third_party_id ? ThirdParty::find($document->third_party_id)?->only(['id', 'name', 'tax_id']) : null), 'entries' => JournalEntry::where('source_document_id', $document->id)->orderByDesc('created_at')->get(['id', 'reference', 'status', 'source_revision', 'version']), 'versions' => SourceVersion::where('source_document_id', $document->id)->orderByDesc('revision')->get(['id', 'revision', 'hash', 'created_at']),
            'origin_review' => $incident ? ['incident_id' => $incident->id, 'observed_revision' => $incident->details['revision'] ?? null, 'registered_revision' => $document->source_revision, 'financial_projection_preserved' => (bool) ($incident->details['financial_projection_preserved'] ?? false)] : null];
    }
}
