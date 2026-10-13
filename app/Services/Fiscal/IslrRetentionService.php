<?php

namespace App\Services\Fiscal;

use App\Models\Tenant\{Company, Document, DocumentReceivedRetention, Person};
use App\Services\ExchangeRates\ExchangeRateMath;

/** Received ISLR uses declared voucher facts, never automatic historical concept rates. */
final class IslrRetentionService extends ReceivedRetentionService
{
    protected const KIND = 'ISLR';
    protected const PREVISION = 'islr_retention_prevision';
    protected const RELATION = 'islr_operations';
    protected const OPERATION_MODEL = \App\Models\Tenant\DocumentIslrRetentionOperation::class;
    protected const HKA = HkaIslrRetention::class;

    public static function context(array $input): array
    {
        FiscalDocumentPersistence::assertNoSecrets($input);
        return validator($input, ['concept_basis'=>'required|string|max:255|regex:/\S/',
            'evidence_reference'=>'required|string|max:255|regex:/\S/'])->validate();
    }

    public static function concepts(): array
    {
        return \DB::connection('tenant')->table('cat_retention_concept')->where('id','!=','001')
            ->orderBy('id')->get(['id','description'])->map(fn($row)=>(array)$row)->all();
    }

    private static function concept(string $id): array
    {
        $concept = \DB::connection('tenant')->table('cat_retention_concept')->where('id',$id)->first();
        if (!$concept || $id === '001') FiscalAmounts::error('concept_id','Seleccione un concepto ISLR no salarial respaldado por la operación.');
        return ['id'=>$concept->id,'description'=>$concept->description];
    }

    protected static function validationRules(): array
    {
        return ['operation_key'=>'required|uuid','voucher_number'=>'required|string|max:50|regex:/\S/',
            'voucher_date'=>'required|date_format:Y-m-d','agent_rif'=>'required|string|max:12',
            'invoice_number'=>'required|string|max:40','invoice_control'=>'required|string|max:32',
            'concept_id'=>'required|string|regex:/^[0-9]{3}$/D', 'base'=>'required|numeric|gt:0|max:9999999999.99',
            'percentage'=>'required|numeric|gt:0|max:100','subtrahend'=>'nullable|numeric|min:0|max:9999999999.99',
            'amount'=>'required|numeric|gt:0|max:9999999999.99','currency_type_id'=>'required|in:VES,USD','exchange_rate'=>'required|numeric|gt:0',
            'attachment'=>'nullable|string|max:255','upload_id'=>'nullable|uuid','fiscal_context'=>'required|array'];
    }

    public static function proposal(int $customerId, string $date, $net, string $documentCurrency, array $input, $invoiceRate = 1): array
    {
        $data = validator($input, ['concept_id'=>'required|string|regex:/^[0-9]{3}$/D',
            'base'=>'nullable|numeric|gt:0|max:9999999999.99','base_mode'=>'nullable|in:full_net,declared',
            'percentage'=>'required|numeric|gt:0|max:100','subtrahend'=>'nullable|numeric|min:0|max:9999999999.99',
            'currency_type_id'=>'required|in:VES,USD','exchange_rate'=>'required|numeric|gt:0',
            'fiscal_context'=>'required|array'])->validate();
        $context = static::context($data['fiscal_context']);
        $concept = static::concept($data['concept_id']);
        $rate = ExchangeRateMath::rate($data['exchange_rate']);
        $mode = $data['base_mode'] ?? (isset($data['base']) ? 'declared' : 'full_net');
        if ($mode === 'declared' && !isset($data['base'])) FiscalAmounts::error('base','Indique la base declarada del comprobante.');
        $base = $mode === 'full_net' ? FiscalAmounts::convert(FiscalAmounts::convert($net,$documentCurrency,'VES',$invoiceRate),'VES',$data['currency_type_id'],$rate) : $data['base'];
        $base = ExchangeRateMath::finalAmount($base);
        $percentage = ExchangeRateMath::finalAmount($data['percentage']);
        $subtrahend = ExchangeRateMath::finalAmount($data['subtrahend'] ?? 0);
        $fiscalNet = FiscalAmounts::convert($net,$documentCurrency,'VES',$invoiceRate);
        if (FiscalAmounts::convert($base,$data['currency_type_id'],'VES',$rate) > $fiscalNet + 0.01) FiscalAmounts::error('base','La base ISLR supera el valor neto fiscal conservado.');
        $retained = ExchangeRateMath::rational($base)->multipliedBy($percentage)->dividedBy(100)->minus($subtrahend);
        if ($retained->isLessThan(0)) $retained = ExchangeRateMath::rational(0);
        $amount = ExchangeRateMath::finalAmount($retained);
        $issuer = FiscalProfileService::at(Company::firstOrFail(),$date);
        $buyer = FiscalProfileService::at(Person::whereType('customers')->findOrFail($customerId),$date);
        $decision = SpecialTaxpayerPolicy::retention($issuer,$buyer,$date,[]);
        $eligible = $decision['islr_eligible'] && (float)$amount > 0 && (float)$base > 0;
        return ['eligible'=>$eligible,'concept_id'=>$concept['id'],'concept'=>$concept,'base'=>$base,'base_mode'=>$mode,
            'percentage'=>$percentage,'subtrahend'=>$subtrahend,'voucher_amount'=>$amount,
            'amount'=>ExchangeRateMath::finalAmount(FiscalAmounts::convert($amount,$data['currency_type_id'],$documentCurrency,$rate)),
            'currency_type_id'=>$documentCurrency,'voucher_currency_type_id'=>$data['currency_type_id'],'exchange_rate'=>$rate,
            'agent_rif'=>$buyer['rif'] ?? '', 'date'=>$date,'fiscal_context'=>$context,
            'issuer_version_id'=>$issuer['version_id'] ?? null,'buyer_version_id'=>$buyer['version_id'] ?? null,
            'diagnostic'=>$eligible ? null : 'El cliente no acredita facultad ISLR vigente o no hay importe positivo.'];
    }

    public static function savePrevision(Document $document, ?array $input): void
    {
        if ($input === null) return;
        if (!static::available($document)) FiscalAmounts::error('islr_retention_prevision','Esta instalación no tiene habilitado el flujo ISLR.');
        static::authorize($document);
        $enabled = validator($input,['enabled'=>'required|boolean'])->validate()['enabled'];
        $value = null;
        if ($enabled) {
            if ($document->document_type_id !== '01' || $document->isVoidedOrRejected()) FiscalAmounts::error('document','La previsión ISLR requiere una factura vigente.');
            if ($document->received_retentions()->where('tax_kind','ISLR')->where('application_status','applied')->exists()) FiscalAmounts::error('islr_retention_prevision','La factura ya tiene ISLR aplicado; no duplique la previsión.');
            $value = static::proposal($document->customer_id,$document->date_of_issue->format('Y-m-d'),$document->total_value,
                $document->currency_type_id,$input,$document->exchange_rate_sale);
            if (!$value['eligible']) FiscalAmounts::error('customer_id',$value['diagnostic']);
            $value += ['actor_id'=>auth()->id(),'saved_at'=>now()->toIso8601String()];
        }
        $document->fiscal_data()->update(['islr_retention_prevision'=>$value ? json_encode($value,JSON_THROW_ON_ERROR) : null]);
        $document->unsetRelation('fiscal_data');
    }

    public static function refreshPrevision(Document $document): void
    {
        if (!static::available($document)) return;
        $saved = $document->fiscal_data->islr_retention_prevision ?? null;
        if (!$saved) return;
        $input = $saved;
        $input['currency_type_id'] = $saved['voucher_currency_type_id'];
        try {
            $proposal = static::proposal($document->customer_id,$document->date_of_issue->format('Y-m-d'),$document->total_value,
                $document->currency_type_id,$input,$document->exchange_rate_sale);
            $value = $proposal['eligible'] ? array_replace($saved,$proposal) : null;
        } catch (\Illuminate\Validation\ValidationException $exception) { $value = null; }
        $document->fiscal_data()->update(['islr_retention_prevision'=>$value ? json_encode($value,JSON_THROW_ON_ERROR) : null]);
        $document->unsetRelation('fiscal_data');
    }

    protected static function validatePending(Document $document, array $input): void
    {
        $date = $input['voucher_date'];
        if ($date < $document->date_of_issue->format('Y-m-d') || $date > now()->format('Y-m-d')) FiscalAmounts::error('voucher_date','El comprobante debe corresponder a la factura y no ser futuro.');
        $proposal = static::proposal($document->customer_id,$date,$document->total_value,$document->currency_type_id,
            $input+['base_mode'=>'declared'],$document->exchange_rate_sale);
        if (!$proposal['eligible'] || $input['agent_rif'] !== $proposal['agent_rif']) FiscalAmounts::error('agent_rif','El agente o la operación no acreditan facultad ISLR vigente.');
        if (!ExchangeRateMath::rational($input['amount'])->isEqualTo(ExchangeRateMath::rational($proposal['voucher_amount']))) FiscalAmounts::error('amount','El importe no coincide con base, porcentaje y sustraendo declarados.');
        if ((float)$proposal['amount'] > $document->balance + 0.005) FiscalAmounts::error('amount','La retención supera el saldo disponible.');
        static::validateSupport($document,$input);
    }

    protected static function appliedTaxableBase(Document $document, DocumentReceivedRetention $retention): float
    {
        return (float)$retention->base;
    }
}
