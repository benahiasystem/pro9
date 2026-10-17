<?php

namespace App\Services\Fiscal;

use App\Http\Controllers\Tenant\DocumentFiscalController;
use App\Models\Tenant\{Company, Document, DocumentReceivedRetention, Person, ReceivedRetentionUpload, User};
use App\Services\ExchangeRates\ExchangeRateMath;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Support\Exact\Decimal as D;
use Illuminate\Validation\ValidationException;

/** IVA-specific eligibility and amounts; lifecycle is shared with ISLR. */
final class IvaRetentionService extends ReceivedRetentionService
{
    protected const KIND = 'IVA';
    protected const PREVISION = 'iva_retention_prevision';
    protected const RELATION = 'operations';
    protected const OPERATION_MODEL = \App\Models\Tenant\DocumentIvaRetentionOperation::class;
    protected const HKA = HkaIvaRetention::class;

    protected static function validationRules(): array
    {
        return [
            'operation_key' => 'required|uuid', 'voucher_number' => 'required|string|regex:/^[0-9]{14}$/',
            'voucher_date' => 'required|date_format:Y-m-d', 'agent_rif' => 'required|string|max:12',
            'invoice_number' => 'required|string|max:40', 'invoice_control' => 'required|string|max:32',
            'base' => 'required|numeric|gt:0', 'percentage' => 'required|numeric|in:75,100',
            'amount' => 'required|numeric|gt:0', 'subtrahend' => 'nullable|numeric|in:0',
            'currency_type_id' => 'required|in:VES,USD', 'exchange_rate' => 'required|numeric|gt:0',
            'attachment' => 'nullable|string|max:255', 'upload_id' => 'nullable|uuid', 'fiscal_context' => 'nullable|array',
        ];
    }

    public static function context(array $input): array
    {
        FiscalDocumentPersistence::assertNoSecrets($input);
        return validator($input, [
            'iva_100_basis' => 'nullable|in:'.implode(',', SpecialTaxpayerPolicy::FULL_IVA),
            'evidence_reference' => 'nullable|string|max:255', 'exclusion' => 'nullable|string|max:255',
            'public_operation_scope' => 'nullable|in:subject,excluded', 'public_scope_reference' => 'nullable|string|max:255',
        ])->validate();
    }

    public static function proposal(int $customerId, string $date, $iva, $taxable, string $currency, array $context = []): array
    {
        $context = static::context($context);
        $issuer = FiscalProfileService::at(Company::firstOrFail(), $date);
        $buyer = FiscalProfileService::at(Person::whereType('customers')->findOrFail($customerId), $date);
        $decision = SpecialTaxpayerPolicy::retention($issuer, $buyer, $date, $context);
        if (\App\Support\Exact\Decimal::of($iva)->isLessThanOrEqualTo(0)) { $decision['eligible']=false; $decision['percentage']=0; }
        $amount = $decision['eligible'] ? ExchangeRateMath::rational($iva)->multipliedBy($decision['percentage'])->dividedBy(100) : 0;
        return ['eligible' => $decision['eligible'], 'percentage' => $decision['percentage'],
            'amount' => \App\Support\Exact\Decimal::represent($amount), 'iva_amount' => \App\Support\Exact\Decimal::represent($iva),
            'calculation_snapshot' => \App\Support\Exact\Decimal::snapshot(['amount'=>$amount,'iva_amount'=>$iva,'taxable_base'=>$taxable]),
            'taxable_base' => \App\Support\Exact\Decimal::represent($taxable), 'currency_type_id' => $currency,
            'agent_rif' => $buyer['rif'] ?? '', 'date' => $date, 'fiscal_context' => $context,
            'issuer_version_id' => $issuer['version_id'] ?? null, 'buyer_version_id' => $buyer['version_id'] ?? null];
    }

    public static function savePrevision(Document $document, ?array $input): void
    {
        if ($input === null) return;
        if (!static::available($document)) FiscalAmounts::error('iva_retention_prevision', 'Esta instalación no tiene habilitado el flujo IVA.');
        static::authorize($document);
        $data = validator($input, ['enabled' => 'required|boolean', 'fiscal_context' => 'nullable|array'])->validate();
        $prevision = null;
        if ($data['enabled']) {
            if ($document->document_type_id !== '01' || $document->total_igv <= 0 || $document->isVoidedOrRejected()) FiscalAmounts::error('document', 'La previsión IVA requiere una factura vigente con IVA.');
            $prevision = static::proposal($document->customer_id, $document->date_of_issue->format('Y-m-d'), $document->exactValue('total_igv'),
                $document->exactValue('total_taxed'), $document->currency_type_id, $data['fiscal_context'] ?? []);
            if (!$prevision['eligible']) FiscalAmounts::error('customer_id', 'El cliente o la operación no acreditan retención IVA.');
            $prevision += ['actor_id' => auth()->id(), 'saved_at' => now()->toIso8601String()];
        }
        $document->fiscal_data()->update(['iva_retention_prevision' => $prevision === null ? null : json_encode($prevision, JSON_THROW_ON_ERROR)]);
        $document->unsetRelation('fiscal_data');
    }

    public static function refreshPrevision(Document $document): void
    {
        if (!static::available($document)) return;
        $saved = $document->fiscal_data->iva_retention_prevision ?? null;
        if (!$saved) return;
        $proposal = static::proposal($document->customer_id,$document->date_of_issue->format('Y-m-d'),$document->exactValue('total_igv'),
            $document->exactValue('total_taxed'),$document->currency_type_id,$saved['fiscal_context'] ?? []);
        $value = $proposal['eligible'] && $document->total_igv > 0 ? array_replace($saved,$proposal) : null;
        $document->fiscal_data()->update(['iva_retention_prevision'=>$value ? json_encode($value,JSON_THROW_ON_ERROR) : null]);
        $document->unsetRelation('fiscal_data');
    }

    protected static function validatePending(Document $document, array $input): void
    {
        if ($document->total_igv <= 0) FiscalAmounts::error('document','La factura no tiene IVA sujeto a retención.');
        $date = $input['voucher_date'];
        if ($date < $document->date_of_issue->format('Y-m-d') || $date > now()->format('Y-m-d')
            || substr($input['voucher_number'],0,6) !== str_replace('-', '', substr($date,0,7))) FiscalAmounts::error('voucher_date', 'La fecha y AAAAMM del comprobante deben corresponder a la factura y no ser futuros.');
        $proposal = static::proposal($document->customer_id, $date, $document->exactValue('total_igv'), $document->exactValue('total_taxed'), $document->currency_type_id, $input['fiscal_context']);
        if (!$proposal['eligible'] || !D::of($input['percentage'])->isEqualTo(D::of($proposal['percentage'])) || $input['agent_rif'] !== $proposal['agent_rif']) FiscalAmounts::error('agent_rif', 'El agente, porcentaje u operación no corresponden al perfil fiscal vigente.');
        $issuer = FiscalProfileService::at(Company::firstOrFail(), $date);
        if ($proposal['percentage'] === 100 && empty($input['fiscal_context']['evidence_reference']) && empty($issuer['evidence_reference'])) FiscalAmounts::error('fiscal_context', 'El 100 % requiere evidencia.');
        $expected = ExchangeRateMath::finalAmount(ExchangeRateMath::rational($input['base'])->multipliedBy($input['percentage'])->dividedBy(100));
        if (!ExchangeRateMath::rational($input['amount'])->isEqualTo(ExchangeRateMath::rational($expected))) FiscalAmounts::error('amount', 'El importe debe corresponder al porcentaje del IVA retenido.');
        $base = D::convert($input['base'], $input['currency_type_id'], $document->currency_type_id, $input['exchange_rate']);
        $applied = D::convert($input['amount'], $input['currency_type_id'], $document->currency_type_id, $input['exchange_rate']);
        $fiscalIva = D::convert(FiscalNote::remainingExact($document,'total_igv'),$document->currency_type_id,'VES',$document->exchange_rate_sale);
        if ($fiscalIva->isLessThanOrEqualTo(0)) FiscalAmounts::error('base','El IVA fiscal en VES debe ser positivo a dos decimales.');
        $voucherIva = D::convert($input['base'],$input['currency_type_id'],'VES',$input['exchange_rate']);
        $voucherAmount = D::convert($input['amount'],$input['currency_type_id'],'VES',$input['exchange_rate']);
        if ($voucherIva->isGreaterThan($fiscalIva->plus('0.01')) || $voucherAmount->isGreaterThan(D::of(D::represent($fiscalIva->multipliedBy($proposal['percentage'])->dividedBy(100),2))->plus('0.01'))
            || $applied->isGreaterThan(D::of(static::settlementLimit($document))->plus('0.005'))) FiscalAmounts::error('amount', 'La retención supera el IVA fiscal conservado o el saldo disponible.');
        static::voucherTaxableBase($document,$input['base'],$input['currency_type_id'],$input['exchange_rate']);
        static::validateSupport($document,$input);
    }

    public static function voucherTaxableBase(Document $document, $iva, string $currency, $rate): string
    {
        return D::represent(self::voucherTaxableBaseExact($document,$iva,$currency,$rate));
    }

    private static function voucherTaxableBaseExact(Document $document, $iva, string $currency, $rate): \Brick\Math\BigRational
    {
        $fiscalIva = D::convert(FiscalNote::remainingExact($document,'total_igv'),$document->currency_type_id,'VES',$document->exchange_rate_sale);
        if ($fiscalIva->isLessThanOrEqualTo(0)) FiscalAmounts::error('base','La factura no tiene IVA fiscal positivo.');
        $voucherIva = D::convert($iva,$currency,'VES',$rate);
        $document->loadMissing('taxes');
        $rates=$document->taxes->where('tax_kind','IVA')->where('amount','>',0)->pluck('percentage')->unique();
        if ($rates->count() > 1 && $voucherIva->minus($fiscalIva)->abs()->isGreaterThan('0.01')) {
            FiscalAmounts::error('base','Con alícuotas mixtas el comprobante debe cubrir el IVA completo; falta un desglose para determinar una base imponible parcial.');
        }
        $fiscalBase=D::convert(FiscalNote::remainingExact($document,'total_taxed'),$document->currency_type_id,'VES',$document->exchange_rate_sale);
        $taxable=$fiscalBase->multipliedBy($voucherIva)->dividedBy($fiscalIva);
        return D::convert($taxable,'VES',$currency,$rate);
    }

    protected static function appliedTaxableBase(Document $document, DocumentReceivedRetention $retention): \Brick\Math\BigRational
    {
        return static::voucherTaxableBaseExact($document,$retention->exactValue('base'),$retention->currency_type_id,$retention->exchange_rate);
    }
}
