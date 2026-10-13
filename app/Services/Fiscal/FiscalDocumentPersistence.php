<?php
namespace App\Services\Fiscal;

use App\Models\Tenant\{Company, Document, DocumentPayment, Establishment, Person, PaymentMethodType};
use App\Services\SalesCustomerIdentityPolicy;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

// ######## INICIO PERSISTENCIA FISCAL VENEZUELA ########
final class FiscalDocumentPersistence
{
    public static function issuer(Company $company, array $establishment): array
    {
        return array_merge(array_intersect_key($establishment, array_flip(['country_id','address','email','telephone','web_address'])),
            $company->only(['number','name','trade_name','logo','identity_document_type_id']));
    }

    public static function initialize(Document $document, Company $company): void
    {
        $establishment = Establishment::findOrFail($document->establishment_id);
        $document->establishment = \App\CoreFacturalo\Requests\Inputs\Common\EstablishmentInput::set($establishment->id);
        $person = Person::findOrFail($document->customer_id);
        $document->customer = array_replace((array)$document->customer, $person->only(['number','name','identity_document_type_id','external_identity_type','email','telephone']));
        $document->customer = self::withHkaIdentity((array) $document->customer);
        $document->issuer = self::issuer($company, (array) $document->establishment);
        $date = \Carbon\Carbon::parse($document->date_of_issue)->format('Y-m-d');
        $document->issuer = array_replace($document->issuer, ['fiscal_profile'=>FiscalProfileService::at($company, $date)]);
        $document->customer = self::withHkaIdentity(array_replace((array)$document->customer, ['fiscal_profile'=>FiscalProfileService::at($person, $date)]));
        if (!empty($document->issuer['fiscal_profile']) && !$document->issuer['fiscal_profile']['commercial_activity']) FiscalAmounts::error('issuer', 'El emisor no tiene actividad comercial habilitada.');
        $document->exchange_rate_date = $document->exchange_rate_date ?? $document->date_of_issue;
        $document->exchange_rate_source = $document->exchange_rate_source ?? 'manual';
        $document->exchange_rate_sale = \App\Services\ExchangeRates\ExchangeRateMath::rate($document->exchange_rate_sale, 'exchange_rate_sale');
        FiscalAmounts::convert(1, $document->currency_type_id, 'VES', $document->exchange_rate_sale);
    }

    public static function withHkaIdentity(array $customer): array
    {
        $type = $customer['identity_document_type_id'] ?? '';
        if ($type === 'ND') {
            $customer['fiscal_profile'] = [];
            $customer['hka_identity_code'] = $customer['external_identity_type'] ?? null;
            return $customer;
        }
        $profile = (array)($customer['fiscal_profile'] ?? []);
        if (!empty($profile['rif'])) {
            $customer['fiscal_profile'] = $profile;
            $customer['hka_identity_code'] = substr($profile['rif'], 0, 1);
            return $customer;
        }
        $customer['hka_identity_code'] = preg_match('/^([VJEGCR])[-0-9]/i', $customer['number'] ?? '', $match)
            ? strtoupper($match[1]) : (['1'=>'V','7'=>'P','E'=>'E','C'=>'C','G'=>'G','R'=>'R'][$type] ?? null);
        return $customer;
    }

    public static function line(array $line, bool $exportation = false): array
    {
        $affectation = $line['affectation_igv_type_id'] ?? null;
        if (!in_array($affectation, $exportation ? ['EXP'] : ['10','20'], true)) FiscalAmounts::error('items', $exportation ? 'Seleccione Exportación — IVA 0 %.' : 'Seleccione Gravado o Exento.');
        if ($exportation && ($line['item']['unit_type_id'] ?? '') === 'SERV') FiscalAmounts::error('items', 'La exportación de servicios no está habilitada.');
        validator($line, ['quantity'=>'required|numeric|gt:0', 'unit_price'=>'required|numeric|min:0'])->validate();
        $percentage = $affectation === '10' ? \App\Support\Venezuela\Localization::taxPercentage() : 0;
        $unit = $affectation === '10' ? $line['unit_price'] / (1 + $percentage / 100) : $line['unit_price'];
        $gross = $unit * $line['quantity'];
        $discountBase = $discountOther = $charges = 0;
        $line['discounts'] = $line['discounts'] ?? [];
        $line['charges'] = $line['charges'] ?? [];
        foreach ($line['discounts'] as &$discount) {
            $discount = (array) $discount;
            $onBase = ($discount['discount_type_id'] ?? '') === '00';
            $discount['base'] = round($onBase ? $gross : $line['unit_price'] * $line['quantity'], 2);
            $amount = !empty($discount['is_amount']) || !empty($discount['from_global_distribution'])
                ? ($discount['amount'] ?? 0) : $discount['base'] * ($discount['factor'] ?? (($discount['percentage'] ?? 0) / 100));
            if (!is_numeric($amount) || $amount < 0) FiscalAmounts::error('items.discounts','Descuento inválido.');
            $discount['amount'] = round($amount, 2);
            if ($onBase) $discountBase += $discount['amount']; else $discountOther += $discount['amount'];
        }
        unset($discount);
        foreach ($line['charges'] as &$charge) {
            $charge = (array) $charge;
            $charge['base'] = round($gross, 2);
            $charge['amount'] = round($gross * ($charge['factor'] ?? (($charge['percentage'] ?? 0) / 100)), 2);
            if ($charge['amount'] < 0) FiscalAmounts::error('items.charges','Cargo inválido.');
            $charges += $charge['amount'];
        }
        unset($charge);
        $base = round($gross - $discountBase, 2);
        $line['unit_value'] = round($unit, 6);
        $line['total_base_igv'] = $base;
        $line['total_discount'] = round($discountBase + $discountOther, 2);
        $line['total_charge'] = round($charges, 2);
        $line['total_value'] = round($gross - $discountBase - $discountOther + $charges, 2);
        if ($base < 0 || $line['total_value'] < 0) FiscalAmounts::error('items','El descuento supera el valor de la línea.');
        if (($line['total_other_taxes'] ?? 0) != 0) FiscalAmounts::error('items.total_other_taxes','Los tributos adicionales deben registrarse en el detalle del documento.');
        $percentage = $affectation === '10' ? \App\Support\Venezuela\Localization::taxPercentage() : 0;
        $line['total_other_taxes'] = 0;
        $line['total_base_other_taxes'] = 0;
        $line['percentage_other_taxes'] = 0;
        $line['percentage_igv'] = $percentage;
        $line['total_igv'] = round($base * $percentage / 100, 2);
        $line['total_taxes'] = round($line['total_igv'] + ($line['total_other_taxes'] ?? 0), 2);
        $line['total'] = round($line['total_value'] + $line['total_taxes'], 2);
        $rateCode = in_array($affectation, ['20', 'EXP'], true) ? 'E' : (['8'=>'R','16'=>'G','31'=>'A'][(string)$percentage] ?? null);
        $line['iva_rate'] = ['code'=>$rateCode,'percentage'=>$percentage];
        unset($line['item']['cod_digemid']);
        $line['item']['hka_unit_code'] = DB::connection('tenant')->table('cat_unit_types')->where('id', $line['item']['unit_type_id'])->where('active',1)->value('hka_code');
        if (!$line['item']['hka_unit_code']) FiscalAmounts::error('items.unit_type_id','Seleccione una unidad activa con equivalencia HKA.');
        return isset($line['item_id']) ? FiscalNoteInventory::capture($line) : $line;
    }

    public static function fiscalData(Document $document, array $input = []): void
    {
        $db = $document->getConnection();
        $relatedIgtf=($document->note->note_debit_type_id ?? null) === 'IGTF' && !empty($document->note->effect_snapshot['invoice_fiscal_data']);
        $inherits=FiscalNote::ordinary($document) || $relatedIgtf;
        if ($document->note && $document->note->affected_document_id) {
            $affected = Document::findOrFail($document->note->affected_document_id);
            if (\App\Services\ExportInvoicePolicy::isExport($affected->invoice->operation_type_id ?? '')) FiscalAmounts::error('note', 'Las notas de exportación todavía no están habilitadas.');
            if (!$inherits && \App\Services\SpecialTaxRegimePolicy::applies($affected)) FiscalAmounts::error('note', 'Las notas vinculadas a regímenes especiales todavía no están habilitadas.');
            if (!$inherits && \App\Services\ThirdPartyInvoicePolicy::isThirdParty($affected)) FiscalAmounts::error('note', 'Las notas de facturas por cuenta de terceros todavía no están habilitadas.');
            if ($affected->document_type_id !== '01' || $affected->customer_id !== $document->customer_id || $affected->establishment_id !== $document->establishment_id || $affected->fiscal_environment !== $document->fiscal_environment || $affected->currency_type_id !== $document->currency_type_id) FiscalAmounts::error('note','La factura afectada debe corresponder al cliente, sucursal, ambiente y moneda de la nota.');
            $document->note->data_affected_document = $affected->only(['document_type_id','series','number','date_of_issue','total','control_number','currency_type_id','exchange_rate_sale']);
            $document->note->save();
        }
        if ($inherits) {
            $original=$document->note->effect_snapshot['invoice_fiscal_data'] ?? [];
            $input=array_replace($original,$input);
        }
        self::assertNoSecrets($input);
        $snapshot = [];
        $operation = $document->invoice->operation_type_id ?? '0101';
        if (\App\Services\ExportInvoicePolicy::isExport($operation)) {
            $snapshot['operation_type'] = (array) $db->table('cat_operation_types')->where('id', $operation)->where('active', 1)->first();
            if (empty($snapshot['operation_type'])) FiscalAmounts::error('operation_type_id', 'Seleccione un INCOTERM activo.');
        }
        foreach (['provider_type_id' => 'cat_providers_types', 'transaction_type_id' => 'cat_transactions_types', 'special_tax_regime_id' => 'cat_special_tax_regime'] as $key => $table) {
            $id = $input[$key] ?? ($key === 'transaction_type_id' ? '01' : null);
            if ($inherits && $key !== 'transaction_type_id') {
                if (isset($original['catalog_snapshot'][$key])) $snapshot[$key]=$original['catalog_snapshot'][$key];
                continue;
            }
            if ($id !== null) {
                $row = $db->table($table)->where('id', $id)->where('active', 1)->first();
                if (!$row) FiscalAmounts::error('fiscal_data.'.$key, 'Seleccione un valor fiscal activo.');
                if ($key === 'special_tax_regime_id') \App\Services\SpecialTaxRegimePolicy::description($id, (array) $row);
                $snapshot[$key] = (array) $row;
            }
        }
        $document->fiscal_data()->updateOrCreate(['document_id' => $document->id], [
            'provider_type_id' => $input['provider_type_id'] ?? null,
            'transaction_type_id' => $input['transaction_type_id'] ?? '01',
            'special_tax_regime_id' => $input['special_tax_regime_id'] ?? null,
            'catalog_snapshot' => $snapshot, 'third_party' => $input['third_party'] ?? null,
            'third_party_person_id' => $input['third_party_person_id'] ?? null,
            'conditional_data' => $input['conditional_data'] ?? null,
        ]);
        $document->emission()->firstOrCreate(['document_id' => $document->id], [
            'fiscal_environment' => $document->fiscal_environment, 'operation_key' => (string) Str::uuid(),
        ]);
    }

    public static function applySettlements(Document $document,array $input): void
    {
        validator($input,['iva_retention_prevision'=>'nullable|array','islr_retention_prevision'=>'nullable|array','received_retentions'=>'nullable|array','received_retentions.*'=>'array'])->validate();
        IvaRetentionService::savePrevision($document, $input['iva_retention_prevision'] ?? null);
        IslrRetentionService::savePrevision($document,$input['islr_retention_prevision'] ?? null);
        foreach ($input['received_retentions'] ?? [] as $retention) self::retention($document,$retention);
        if (!empty($input['guarantee_fund'])) {
            $g=validator($input['guarantee_fund'],['amount'=>'required|numeric|min:0','base'=>'required|numeric|min:0','percentage'=>'required|numeric|min:0|max:100'])->validate();
            foreach ($g as &$value) $value=round((float)$value,2);
            unset($value);
            if (abs($g['amount']-round($g['base']*$g['percentage']/100,2))>0.005) FiscalAmounts::error('guarantee_fund','El importe no coincide con la base y porcentaje del fondo.');
            $document=$document->fresh();
            if ($g['amount']>$document->balance+$document->guarantee_amount+0.005) FiscalAmounts::error('guarantee_fund','El fondo supera el saldo disponible.');
            $document->guarantee_fund()->updateOrCreate(['document_id'=>$document->id],$g);
        }
        if ($document->document_type_id !== '07' && $document->fresh()->balance < -0.005) FiscalAmounts::error('document','Las aplicaciones superan el saldo del documento.');
        self::updateSettlementState($document);
    }

    public static function assertNoSecrets(array $data): void
    {
        foreach ($data as $key=>$value) {
            if (preg_match('/(?:token|jwt|password|clave|credential|secret)/i',(string)$key)) FiscalAmounts::error('fiscal_data','No se guardan credenciales ni tokens en datos fiscales.');
            if (is_array($value)) self::assertNoSecrets($value);
        }
    }

    public static function assertMutable(Document $document): void
    {
        $status = $document->emission()->value('status');
        if ($status && $status !== 'not_requested') FiscalAmounts::error('document', 'El documento tiene una operación fiscal preparada y no puede editarse.');
    }

    public static function otherTaxes(Document $document, array $taxes): void
    {
        $document->taxes()->where('tax_kind','OTI')->delete();
        foreach ($taxes as $input) {
            $data = validator($input, ['code'=>'required|string|max:32|regex:/^[A-Za-z0-9_-]+$/','percentage'=>'required|numeric|min:0|max:100','base'=>'required|numeric|min:0'])->validate();
            $data['percentage'] = round($data['percentage'], 2);
            $data['base'] = round($data['base'], 2);
            $data['amount'] = round($data['base'] * $data['percentage'] / 100, 2);
            $data['tax_kind'] = 'OTI';
            $data['base_ves'] = FiscalAmounts::convert($data['base'],$document->currency_type_id,'VES',$document->exchange_rate_sale);
            $data['amount_ves'] = FiscalAmounts::convert($data['amount'],$document->currency_type_id,'VES',$document->exchange_rate_sale);
            $document->taxes()->create($data);
        }
    }

    public static function summarize(Document $document): void
    {
        $exportation = \App\Services\ExportInvoicePolicy::isExport($document->invoice->operation_type_id ?? '');
        $document->taxes()->whereNull('document_payment_id')->where('tax_kind', 'IVA')->delete();
        $items = $document->items()->get();
        $discounts = collect((array)$document->discounts)->map(fn ($r) => (array)$r);
        $charges = collect((array)$document->charges)->map(fn ($r) => (array)$r);
        foreach ($discounts->merge($charges) as $r) {
            if (!is_numeric($r['amount'] ?? null) || $r['amount'] < 0) FiscalAmounts::error('discounts','Importe de descuento o cargo inválido.');
        }
        $distributed = $items->sum(fn ($line) => collect((array)$line->discounts)->sum(fn ($r) => !empty(((array)$r)['from_global_distribution']) ? ((array)$r)['amount'] : 0));
        $taxedDiscount = max(0, $discounts->whereIn('discount_type_id',['02','04'])->sum('amount') - $distributed);
        $exemptDiscount = $discounts->where('discount_type_id','05')->sum('amount');
        $globalDiscount = max(0,$discounts->sum('amount') - $distributed);
        $globalCharge = $charges->sum('amount');
        $taxedOriginal = $items->where('affectation_igv_type_id','10')->sum('total_base_igv');
        $exemptOriginal = $items->where('affectation_igv_type_id','20')->sum('total_base_igv');
        if (!$exportation && ($taxedDiscount > $taxedOriginal || $exemptDiscount > $exemptOriginal)) FiscalAmounts::error('discounts','El descuento supera su base imponible.');
        if ($exportation && $globalDiscount > $items->sum('total_value')) FiscalAmounts::error('discounts', 'El descuento supera el valor exportado.');
        $taxed = $exempt = $iva = 0;
        foreach ($items->groupBy(fn ($line) => ($line->iva_rate['code'] ?? '').':'.$line->percentage_igv) as $group) {
            if ($exportation) continue;
            $line = $group->first();
            $base = round($group->sum('total_base_igv'), 2);
            $deduction = $line->affectation_igv_type_id === '10'
                ? ($taxedOriginal > 0 ? $taxedDiscount * $base / $taxedOriginal : 0)
                : ($exemptOriginal > 0 ? $exemptDiscount * $base / $exemptOriginal : 0);
            $base = round($base - $deduction, 2);
            // Preserve current per-line IVA rounding when there is no global base adjustment.
            $amount = $deduction > 0 ? round($base * $line->percentage_igv / 100, 2) : round($group->sum('total_igv'), 2);
            $document->taxes()->create(['tax_kind'=>'IVA','code'=>$line->iva_rate['code'] ?? 'IVA',
                'percentage'=>$line->percentage_igv,'base'=>$base,'amount'=>$amount,
                'base_ves'=>FiscalAmounts::convert($base,$document->currency_type_id,'VES',$document->exchange_rate_sale),
                'amount_ves'=>FiscalAmounts::convert($amount,$document->currency_type_id,'VES',$document->exchange_rate_sale)]);
            if ($line->affectation_igv_type_id === '10') $taxed += $base; else $exempt += $base;
            $iva += $amount;
        }
        if ($items->isNotEmpty()) {
            $document->total_taxed = round($taxed, 2);
            $document->total_exonerated = round($exempt, 2);
            $document->total_igv = round($iva, 2);
            $document->total_value = round($items->sum('total_value') - $globalDiscount + $globalCharge, 2);
            $document->total_exportation = $exportation ? $document->total_value : 0;
            if ($exportation) {
                $document->taxes()->create(['tax_kind'=>'IVA', 'code'=>'E', 'percentage'=>0, 'base'=>$document->total_exportation, 'amount'=>0,
                    'base_ves'=>FiscalAmounts::convert($document->total_exportation, $document->currency_type_id, 'VES', $document->exchange_rate_sale), 'amount_ves'=>0]);
            }
            $document->total_discount = round($items->sum('total_discount') + $globalDiscount, 2);
            $document->total_charge = round($items->sum('total_charge') + $globalCharge, 2);
        }
        $igtf = $document->taxes()->where('tax_kind','IGTF')->sum('amount');
        $other = $document->taxes()->whereNotIn('tax_kind',['IVA','IGTF'])->sum('amount');
        $document->total_base_other_taxes = round($document->taxes()->whereNotIn('tax_kind',['IVA','IGTF'])->sum('base'), 2);
        $document->total_other_taxes = round($igtf + $other, 2);
        $document->total_taxes = round($document->total_igv + $document->total_other_taxes, 2);
        // Advance discounts 04/05 already reduce bases and IVA; do not deduct them twice.
        $advance = $discounts->whereIn('discount_type_id',['04','05'])->isEmpty() ? $document->total_prepayment : 0;
        $document->total = round($document->total_value + $document->total_taxes - $advance, 2);
        if ($document->total < 0) FiscalAmounts::error('total','El total no puede ser negativo.');
        $document->subtotal = round($document->total - $igtf, 2);
        $legends = collect((array)$document->legends)->map(fn ($r) => (array)$r)->reject(fn ($r) => ($r['code'] ?? null)==='1000')->values()->all();
        $legends[] = ['code'=>'1000','value'=>\App\CoreFacturalo\Helpers\Number\NumberLetter::convertToLetter($document->total)];
        $document->legends = $legends;
        $document->save();
        $breakdown = $document->taxes()->get(['tax_kind','code','percentage','base_ves','amount_ves'])->toArray();
        $values = ['taxed'=>$document->total_taxed,'exempt'=>$document->total_exonerated,'exportation'=>$document->total_exportation,
            'discount'=>$document->total_discount,'charge'=>$document->total_charge,
            'iva'=>$document->total_igv,'other_taxes'=>$document->total_other_taxes,'total'=>$document->total];
        foreach ($values as &$v) $v = FiscalAmounts::convert($v,$document->currency_type_id,'VES',$document->exchange_rate_sale);
        unset($v);
        $document->currency_totals()->updateOrCreate(['document_id'=>$document->id],$values + ['currency_type_id'=>'VES','tax_breakdown'=>$breakdown]);
    }

    public static function payment(Document $document, array $input, bool $initial = false): DocumentPayment
    {
        return $document->getConnection()->transaction(function () use ($document, $input, $initial) {
            validator($input, ['date_of_payment'=>'required|date_format:Y-m-d', 'exception_basis'=>'nullable|string|max:255', 'financial_intermediation'=>'nullable|in:none,financial_institution'])->validate();
            $company = Company::query()->lockForUpdate()->firstOrFail();
            $document = Document::query()->lockForUpdate()->findOrFail($document->id);
            NoteAnnulment::assertNotBusy($document);
            if ($document->isVoidedOrRejected() || ($document->document_type_id !== '01' && !($document->document_type_id === '08' && FiscalNote::ordinary($document)))) FiscalAmounts::error('document','Sólo se cobran facturas vigentes.');
            $key = $input['operation_key'] ?? (string) Str::uuid();
            if (!Str::isUuid($key)) FiscalAmounts::error('operation_key', 'Use un UUID válido.');
            $existing = DocumentPayment::where('operation_key', $key)->first();
            $date = \Carbon\Carbon::parse($input['date_of_payment'] ?? now())->format('Y-m-d');
            $issuer = $existing->fiscal_context['issuer'] ?? FiscalProfileService::at($company, $date);
            $buyer = $existing->fiscal_context['buyer'] ?? FiscalProfileService::at(Person::findOrFail($document->customer_id), $date);
            if ($existing) {
                $decision = $existing->fiscal_context['decision'] ?? [];
                if (($input['financial_intermediation'] ?? null) !== ($decision['financial_intermediation'] ?? null)
                    || ($input['exception_basis'] ?? null) !== ($decision['exception_basis'] ?? null)) FiscalAmounts::error('operation_key', 'La operación ya existe con otro contexto fiscal.');
            } else {
                $decision = SpecialTaxpayerPolicy::payment($issuer, $input, $input['currency_type_id'] ?? $document->currency_type_id, $date);
            }
            $input['igtf_status'] = $input['igtf_status'] ?? ($decision['igtf_status'] ?? 'not_applicable');
            $storedRate = $existing ? \App\Models\Tenant\DocumentTax::where('document_payment_id',$existing->id)->value('percentage') : null;
            $values = FiscalAmounts::payment($input, $document->currency_type_id, $document->exchange_rate_sale,
                $existing ? $existing->igtf_status === 'subject' : (bool)$company->igtf_enabled, $existing ? $storedRate : $company->igtf_rate);
            if ($existing) {
                if ($existing->document_id !== $document->id || $existing->reversed_at ||
                    (float) $existing->original_amount !== $values['original_amount'] || $existing->currency_type_id !== $values['currency_type_id'] ||
                    \App\Services\ExchangeRates\ExchangeRateMath::rate($existing->exchange_rate) !== $values['exchange_rate'] || $existing->exemption_reason !== $values['exemption_reason'] ||
                    $existing->date_of_payment->format('Y-m-d') !== ($input['date_of_payment'] ?? '') ||
                    $existing->reference !== ($input['reference'] ?? null) ||
                    $existing->exchange_rate_source !== $values['exchange_rate_source'] ||
                    optional($existing->exchange_rate_date)->format('Y-m-d') !== ($values['exchange_rate_date'] ? \Carbon\Carbon::parse($values['exchange_rate_date'])->format('Y-m-d') : null) ||
                    $existing->igtf_status !== $values['igtf_status'] || $existing->payment_method_type_id !== ($input['payment_method_type_id'] ?? null)) {
                    FiscalAmounts::error('operation_key', 'La operación ya existe con otros datos.');
                }
                if (isset($input['payment_destination_id']) && $existing->global_payment) {
                    $destination = $existing->global_payment;
                    $cash = $destination->destination_type === \App\Models\Tenant\Cash::class;
                    if (($input['payment_destination_id']==='cash') !== $cash || (!$cash && (string)$destination->destination_id !== (string)$input['payment_destination_id'])) FiscalAmounts::error('operation_key','La operación ya existe con otro destino.');
                }
                return $existing;
            }
            PaymentMethodType::assertActiveForPayment($input['payment_method_type_id'] ?? null);
            if (!$initial && $values['tax_amount'] > 0 && \App\Services\ExportInvoicePolicy::isExport($document->invoice->operation_type_id ?? '')) {
                FiscalAmounts::error('payment', 'El IGTF de un cobro posterior requiere una nota de exportación; ese flujo todavía no está habilitado.');
            }
            if (!$initial && !FiscalNote::ordinary($document) && $values['tax_amount'] > 0 && \App\Services\SpecialTaxRegimePolicy::applies($document)) FiscalAmounts::error('payment', 'El IGTF posterior requiere una nota vinculada a régimen especial; ese flujo todavía no está habilitado.');
            if (!$initial && !FiscalNote::ordinary($document) && $values['tax_amount'] > 0 && \App\Services\ThirdPartyInvoicePolicy::isThirdParty($document)) FiscalAmounts::error('payment', 'El IGTF de un cobro posterior requiere una nota por cuenta de terceros; ese flujo todavía no está habilitado.');
            $method = PaymentMethodType::findOrFail($input['payment_method_type_id']);
            if ($document->document_type_id === '07') FiscalAmounts::error('document','Las NC no reciben cobros; utilice Registrar devolución.');
            if (!$initial && $values['payment'] > $document->balance + 0.005) FiscalAmounts::error('payment', 'El pago supera el saldo disponible.');
            if ($initial && $document->emission()->where('status', '!=', 'not_requested')->exists()) FiscalAmounts::error('emission','No se modifican los pagos de una operación preparada.');
            if (!empty($input['id'])) FiscalAmounts::error('id', 'Revierta el pago y registre uno nuevo; no se editan pagos contabilizados.');
            validator($input, ['date_of_payment'=>'required|date','exchange_rate_source'=>'nullable|string|max:255','exchange_rate_date'=>'nullable|date','exemption_reason'=>'nullable|string|max:255'])->validate();
            $fields = array_intersect_key($input, array_flip(['date_of_payment','payment_method_type_id','has_card','card_brand_id','reference','payment_received','change']));

            $context = $document->getConnection()->getSchemaBuilder()->hasColumn('document_payments', 'fiscal_context')
                ? ['fiscal_context'=>['issuer'=>$issuer, 'buyer'=>$buyer, 'decision'=>$decision, 'date'=>$date]] : [];
            $payment = $document->payments()->create($values + $fields + $context + [
                'operation_key' => $key, 'payment_method_snapshot' => $method->only(['id','description','hka_code']),
                ]);
            if ($values['tax_amount'] > 0) {
                $target = $initial ? $document : self::igtfNote($document, $payment);
                $base = FiscalAmounts::convert($values['original_amount'], $values['currency_type_id'], $target->currency_type_id, $values['exchange_rate']);
                $tax = FiscalAmounts::convert($values['tax_amount'], $values['currency_type_id'], $target->currency_type_id, $values['exchange_rate']);
                $target->taxes()->create(['document_payment_id' => $payment->id, 'tax_kind' => 'IGTF', 'code' => 'IGTF',
                    'percentage' => $company->igtf_rate, 'base' => $base, 'amount' => $tax,
                    'base_ves' => FiscalAmounts::convert($values['original_amount'], $values['currency_type_id'], 'VES', $values['exchange_rate']),
                    'amount_ves' => FiscalAmounts::convert($values['tax_amount'], $values['currency_type_id'], 'VES', $values['exchange_rate'])]);
                self::summarize($target);
                $target->payments()->create(['date_of_payment'=>$payment->date_of_payment,'payment_method_type_id'=>$payment->payment_method_type_id,
                        'currency_type_id'=>$payment->currency_type_id,'exchange_rate'=>$payment->exchange_rate,
                        'original_amount'=>$payment->tax_amount,'payment'=>$tax,'tax_amount'=>0,'receipt_parent_id'=>$payment->id,
                        'operation_key'=>(string) Str::uuid(),'payment_method_snapshot'=>$payment->payment_method_snapshot]);
                self::updateSettlementState($target);
                if (!$initial && HkaIgtfNote::applies($target)) HkaIgtfNote::afterCommit($target);
            }
            self::updateSettlementState($document);
            return $payment;
        });
    }

    private static function igtfNote(Document $invoice, DocumentPayment $payment): Document
    {
        $series = app(\App\Services\SeriesResolver::class)->usableQuery($invoice->establishment_id)->where('document_type_id', '08')->first();
        if (!$series) FiscalAmounts::error('series', 'Configure una serie autorizada de Nota de débito para registrar IGTF posterior.');
        SalesCustomerIdentityPolicy::assertCustomerAllowed($invoice->customer_id);
        $note = new Document($invoice->only(['user_id','establishment_id','establishment','customer_id','customer','currency_type_id','exchange_rate_sale','exchange_rate_source','exchange_rate_date','seller_id']));
        $note->forceFill(['external_id' => (string) Str::uuid(), 'fiscal_environment' => $invoice->fiscal_environment,
            'fiscal_emission_mode'=>$invoice->fiscal_emission_mode, 'payment_condition_id'=>'01',
            'exchange_rate_sale'=>$payment->exchange_rate, 'exchange_rate_source'=>$payment->exchange_rate_source,
            'exchange_rate_date'=>$payment->exchange_rate_date ?? $payment->date_of_payment,
            'group_id' => '01', 'document_type_id' => '08', 'series' => $series->number, 'number' => '#',
            'date_of_issue' => $payment->date_of_payment->format('Y-m-d'), 'time_of_issue' => now()->format('H:i:s'),
            'state_type_id' => '01', 'total' => 0, 'total_value' => 0, 'total_igv' => 0,
            'additional_information' => 'IGTF del pago '.$payment->operation_key]);
        $note->save();
        // New-document initialization uses current settings; this adjustment belongs to the frozen invoice.
        $note->forceFill(['fiscal_emission_mode'=>$invoice->fiscal_emission_mode, 'fiscal_environment'=>$invoice->fiscal_environment]);
        $note->issuer = array_replace($invoice->issuer, ['fiscal_profile'=>$payment->fiscal_context['issuer'] ?? []]);
        $note->customer = self::withHkaIdentity(array_replace((array)$invoice->customer, ['fiscal_profile'=>$payment->fiscal_context['buyer'] ?? []]));
        $note->save();
        $reference=FiscalNote::ordinary($invoice) ? $invoice->note->affected_document : $invoice;
        $note->note()->create(['note_type' => 'debit', 'note_debit_type_id' => 'IGTF', 'note_description' => 'IGTF sobre pago posterior',
            'effect_snapshot'=>FiscalNote::ordinary($invoice) ? ['invoice_fiscal_data'=>$invoice->fiscal_data->only(['provider_type_id','special_tax_regime_id','catalog_snapshot','third_party','third_party_person_id'])] : null,
            'affected_document_id' => $reference->id, 'data_affected_document' => array_merge($reference->only(['series','number','date_of_issue','total','control_number','currency_type_id','exchange_rate_sale']), ['document_type_id'=>'01'])]);
        $note->unsetRelation('note');
        self::fiscalData($note, ['transaction_type_id' => '98']);
        return $note;
    }

    public static function retention(Document $document, array $input)
    {
        if (($input['tax_kind'] ?? '') === 'IVA') return IvaRetentionService::store($document, $input);
        if (($input['tax_kind'] ?? '') === 'ISLR' && IslrRetentionService::available($document)) return IslrRetentionService::store($document,$input);
        return self::retentionValidated($document, $input);
    }

    public static function retentionValidated(Document $document, array $input, ?\App\Models\Tenant\DocumentReceivedRetention $existing = null)
    {
        return $document->getConnection()->transaction(function () use ($document, $input, $existing) {
            Company::query()->lockForUpdate()->firstOrFail();
            $document = Document::query()->lockForUpdate()->findOrFail($document->id);
            $data = validator($input, [
                'tax_kind'=>'required|in:IVA,ISLR', 'voucher_number'=>'required|string|max:100', 'voucher_date'=>'required|date',
                'agent_id'=>'required|integer', 'concept_id'=>'nullable|string|max:4', 'base'=>'required|numeric|gt:0',
                'percentage'=>'required|numeric|gt:0|max:100', 'subtrahend'=>'nullable|numeric|min:0',
                'amount'=>'required|numeric|gt:0', 'currency_type_id'=>'required|in:VES,USD', 'exchange_rate'=>'required|numeric|gt:0',
                'attachment'=>'required|string|max:255', 'agent_rif'=>'required|string|max:12',
                'invoice_control'=>'required|string|max:32', 'invoice_number'=>'required|string|max:40',
                'fiscal_context'=>'nullable|array',
            ])->validate();
            if ($document->received_retentions()->applied()->where('tax_kind',$data['tax_kind'])->where('agent_id',$data['agent_id'])->where('voucher_number',$data['voucher_number'])->when($existing, fn($q) => $q->where('id','!=',$existing->id))->exists()) FiscalAmounts::error('voucher_number','El comprobante ya está registrado.');
            foreach (['base','percentage','subtrahend','amount'] as $field) $data[$field] = round((float)($data[$field] ?? 0),2);
            $data['exchange_rate'] = \App\Services\ExchangeRates\ExchangeRateMath::rate($data['exchange_rate']);
            if ($data['amount']<=0 || $data['base']<=0 || $data['exchange_rate']<=0) FiscalAmounts::error('amount','Importes y tasa deben ser positivos con la precisión del sistema.');
            $agent = Person::findOrFail($data['agent_id']);
            if ($agent->id !== $document->customer_id) FiscalAmounts::error('agent_id', 'El agente debe ser el cliente de la factura.');
            $date = \Carbon\Carbon::parse($data['voucher_date'])->format('Y-m-d');
            if ($date < $document->date_of_issue->format('Y-m-d') || $date > now()->format('Y-m-d')) FiscalAmounts::error('voucher_date', 'El comprobante debe ser posterior o igual a la factura y no futuro.');
            $buyer = FiscalProfileService::at($agent, $date);
            $issuer = FiscalProfileService::at(Company::firstOrFail(), $date);
            if (($buyer['rif'] ?? '') !== $data['agent_rif']) FiscalAmounts::error('agent_rif', 'El RIF del comprobante debe corresponder al perfil vigente del agente.');
            if (!$document->control_number || $data['invoice_control'] !== $document->control_number || $data['invoice_number'] !== $document->number_full) FiscalAmounts::error('invoice_control', 'El comprobante debe referir al número y control fiscal real de la factura.');
            $context = validator($input['fiscal_context'] ?? [], [
                'iva_100_basis'=>'nullable|in:'.implode(',', SpecialTaxpayerPolicy::FULL_IVA),
                'evidence_reference'=>'nullable|string|max:255', 'exclusion'=>'nullable|string|max:255',
                'concept_basis'=>'nullable|string|max:255',
                'public_operation_scope'=>'nullable|in:subject,excluded', 'public_scope_reference'=>'nullable|string|max:255',
            ])->validate();
            $proposal = SpecialTaxpayerPolicy::retention($issuer, $buyer, $date, $context);
            if ($data['tax_kind'] === 'IVA') {
                if (!$proposal['eligible']) FiscalAmounts::error('agent_id', 'El perfil o la operación no acreditan retención IVA.');
                if ($data['percentage'] !== (float)$proposal['percentage'] || $data['subtrahend'] != 0) FiscalAmounts::error('percentage', 'La retención IVA debe usar 75 % o 100 % según su fundamento, sin sustraendo.');
                if ($proposal['percentage'] === 100 && empty($context['evidence_reference']) && empty($issuer['evidence_reference'])) FiscalAmounts::error('fiscal_context', 'El 100 % requiere referencia de evidencia.');
                if (!preg_match('/^[0-9]{14}$/D', $data['voucher_number']) || substr($data['voucher_number'],0,6) !== str_replace('-', '', substr($date,0,7))) FiscalAmounts::error('voucher_number', 'El comprobante IVA requiere AAAAMM y ocho dígitos consecutivos.');
            } elseif (!$proposal['islr_eligible']) FiscalAmounts::error('agent_id', 'El perfil no acredita facultad ISLR para el concepto aplicable.');
            if ($data['tax_kind'] === 'ISLR' && empty($data['concept_id'])) FiscalAmounts::error('concept_id', 'Indique el concepto ISLR.');
            if (!empty($data['concept_id']) && !DB::connection('tenant')->table('cat_retention_concept')->where('id',$data['concept_id'])->exists()) FiscalAmounts::error('concept_id', 'Concepto ISLR inválido.');
            $concept = null;
            if ($data['tax_kind'] === 'ISLR') {
                if ($data['concept_id'] === '001') FiscalAmounts::error('concept_id', 'Las retenciones salariales no corresponden a una factura de venta propia.');
                if (empty($context['concept_basis']) || empty($context['evidence_reference'])) FiscalAmounts::error('fiscal_context', 'Indique el fundamento del concepto ISLR aplicable y su evidencia; SPE no determina ISLR.');
                $concept = (array) DB::connection('tenant')->table('cat_retention_concept')->where('id',$data['concept_id'])->first();
            }
            $expected = round(max(0, $data['base'] * $data['percentage'] / 100 - ($data['subtrahend'] ?? 0)), 2);
            if (abs($data['amount'] - $expected) > 0.01) FiscalAmounts::error('amount','El importe no coincide con base, porcentaje y sustraendo.');
            $applied = FiscalAmounts::convert($data['amount'], $data['currency_type_id'], $document->currency_type_id, $data['exchange_rate']);
            if ($document->document_type_id !== '01' || $document->isVoidedOrRejected()) FiscalAmounts::error('document','Sólo se retiene una factura vigente.');
            if ($applied > ReceivedRetentionService::settlementLimit($document) + 0.005) FiscalAmounts::error('amount','La retención supera el saldo disponible.');
            if ($data['tax_kind']==='IVA') {
                $fiscalIva = FiscalAmounts::convert($document->total_igv,$document->currency_type_id,'VES',$document->exchange_rate_sale);
                $retainedVes = $document->received_retentions()->where('tax_kind','IVA')->applied()->get()->sum(fn($row)=>FiscalAmounts::convert($row->amount,$row->currency_type_id,'VES',$row->exchange_rate));
                $available = round($fiscalIva * $proposal['percentage'] / 100,2) - $retainedVes;
                if (FiscalAmounts::convert($data['base'],$data['currency_type_id'],'VES',$data['exchange_rate']) > $fiscalIva + 0.01
                    || FiscalAmounts::convert($data['amount'],$data['currency_type_id'],'VES',$data['exchange_rate']) > $available + 0.01) FiscalAmounts::error('base','La retención supera el IVA fiscal conservado de la factura.');
            }
            if ($data['tax_kind'] === 'ISLR' && FiscalAmounts::convert($data['base'],$data['currency_type_id'],'VES',$data['exchange_rate']) > FiscalAmounts::convert($document->total_value,$document->currency_type_id,'VES',$document->exchange_rate_sale) + 0.01) FiscalAmounts::error('base', 'La base ISLR supera el valor neto facturado.');
            if (!empty($data['attachment'])) {
                $prefix = 'received_retentions/'.$document->id.'/';
                if (!str_starts_with($data['attachment'], $prefix) || str_contains($data['attachment'],'..') || !Storage::disk('tenant')->exists($data['attachment'])) FiscalAmounts::error('attachment','Adjunto inválido para esta factura y tenant.');
            }
            unset($data['agent_rif'], $data['invoice_control'], $data['invoice_number'], $data['fiscal_context']);
            $data['fiscal_context'] = ['issuer'=>$issuer,'buyer'=>$buyer,'proposal'=>$proposal,'operation'=>$context,
                'concept'=>$concept,
                'invoice_control'=>$document->control_number,'invoice_number'=>$document->number_full,'actor_id'=>$existing->fiscal_context['actor_id'] ?? auth()->id()];
            $values = $data + ['agent' => $agent->only(['number','name','identity_document_type_id','address']) + ['fiscal_profile'=>$buyer], 'agent_number'=>$buyer['rif'],'applied_amount'=>$applied];
            if ($existing) { $existing->fill($values)->save(); $retention = $existing; }
            else $retention=$document->received_retentions()->create($values);
            self::updateSettlementState($document);
            return $retention;
        });
    }

    public static function reverse(DocumentPayment $payment, string $reason): void
    {
        $payment->getConnection()->transaction(function () use ($payment, $reason) {
            Company::query()->lockForUpdate()->firstOrFail();
            $document = Document::query()->lockForUpdate()->findOrFail($payment->document_id);
            NoteAnnulment::assertNotBusy($document);
            $payment = DocumentPayment::query()->lockForUpdate()->findOrFail($payment->id);
            if (\App\Models\Tenant\DocumentNoteRefund::where('document_payment_id',$payment->id)->whereNull('reversed_at')->exists()) FiscalAmounts::error('payment','Revierta las devoluciones asociadas antes de revertir este cobro.');
            if ($payment->receipt_parent_id) FiscalAmounts::error('payment','Revierta el pago que originó este cargo IGTF.');
            if ($payment->reversed_at) return;
            if (trim($reason)==='') FiscalAmounts::error('reason','Indique el motivo de reversión.');
            $payment->cashDocumentPayments()->delete();
            $payment->forceFill(['reversed_at'=>now(),'reversal_reason'=>$reason,'reversed_by'=>auth()->id()])->save();
            DocumentPayment::where('receipt_parent_id',$payment->id)->update(['reversed_at'=>now(),'reversal_reason'=>$reason,'reversed_by'=>auth()->id()]);
            $tax = \App\Models\Tenant\DocumentTax::where('document_payment_id',$payment->id)->first();
            if ($tax) {
                $target = Document::findOrFail($tax->document_id);
                if ($target->id === $document->id) {
                    // Never rewrite the issued invoice when reversing its collection.
                    $target->additional_information = implode('|', array_merge((array)$target->additional_information, ['Pago IGTF revertido: '.$payment->operation_key.' '.$reason]));
                } else {
                    self::assertMutable($target);
                    $target->state_type_id = '11';
                    $target->additional_information = implode('|', array_merge((array)$target->additional_information, ['Reversión: '.$reason]));
                }
                $target->save();
            }
            self::updateSettlementState($document);
        });
    }

    public static function updateSettlementState(Document $document): void
    {
        $document=$document->fresh();
        $document->total_canceled=$document->balance<=0.005;
        if ($document->isDirty('total_canceled')) $document->save();
    }
}
// ######## FIN PERSISTENCIA FISCAL VENEZUELA ########
