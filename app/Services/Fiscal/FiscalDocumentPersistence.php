<?php
namespace App\Services\Fiscal;

use App\Models\Tenant\{Company, Document, DocumentPayment, Establishment, Person, PaymentMethodType};
use App\Services\SalesCustomerIdentityPolicy;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

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
        $document->customer = array_replace((array)$document->customer, $person->only(['number','name','identity_document_type_id','country_id','email','telephone']));
        if (isset($document->customer->number)) {
            $customer = (array) $document->customer;
            $type = $customer['identity_document_type_id'] ?? '';
            $code = preg_match('/^([VJEGCR])[-0-9]/i', $customer['number'], $match) ? strtoupper($match[1]) : (['1'=>'V','7'=>'P','E'=>'E','C'=>'C','G'=>'G','R'=>'R'][$type] ?? null);
            $customer['hka_identity_code'] = $code;
            $document->customer = $customer;
        }
        $document->issuer = self::issuer($company, (array) $document->establishment);
        $document->exchange_rate_date = $document->exchange_rate_date ?? $document->date_of_issue;
        $document->exchange_rate_source = $document->exchange_rate_source ?? 'manual';
        $document->exchange_rate_sale = round((float)$document->exchange_rate_sale,3);
        FiscalAmounts::convert(1, $document->currency_type_id, 'VES', $document->exchange_rate_sale);
    }

    public static function line(array $line): array
    {
        $affectation = $line['affectation_igv_type_id'] ?? null;
        if (!in_array($affectation, ['10','20'], true)) FiscalAmounts::error('items', 'Seleccione Gravado o Exento.');
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
        $rateCode = $affectation === '20' ? 'E' : (['8'=>'R','16'=>'G','31'=>'A'][(string)$percentage] ?? null);
        $line['iva_rate'] = ['code'=>$rateCode,'percentage'=>$percentage];
        unset($line['item']['cod_digemid']);
        $line['item']['hka_unit_code'] = DB::connection('tenant')->table('cat_unit_types')->where('id', $line['item']['unit_type_id'])->where('active',1)->value('hka_code');
        if (!$line['item']['hka_unit_code']) FiscalAmounts::error('items.unit_type_id','Seleccione una unidad activa con equivalencia HKA.');
        return $line;
    }

    public static function fiscalData(Document $document, array $input = []): void
    {
        $db = $document->getConnection();
        if ($document->note && $document->note->affected_document_id) {
            $affected = Document::findOrFail($document->note->affected_document_id);
            if ($affected->document_type_id !== '01' || $affected->customer_id !== $document->customer_id || $affected->establishment_id !== $document->establishment_id || $affected->fiscal_environment !== $document->fiscal_environment || $affected->currency_type_id !== $document->currency_type_id) FiscalAmounts::error('note','La factura afectada debe corresponder al cliente, sucursal, ambiente y moneda de la nota.');
            $document->note->data_affected_document = $affected->only(['document_type_id','series','number','date_of_issue','total','control_number']);
            $document->note->save();
        }
        self::assertNoSecrets($input);
        $snapshot = [];
        foreach (['provider_type_id' => 'cat_providers_types', 'transaction_type_id' => 'cat_transactions_types', 'special_tax_regime_id' => 'cat_special_tax_regime'] as $key => $table) {
            $id = $input[$key] ?? ($key === 'transaction_type_id' ? '01' : null);
            if ($id !== null) {
                $row = $db->table($table)->where('id', $id)->where('active', 1)->first();
                if (!$row) FiscalAmounts::error('fiscal_data.'.$key, 'Seleccione un valor fiscal activo.');
                $snapshot[$key] = (array) $row;
            }
        }
        $document->fiscal_data()->updateOrCreate(['document_id' => $document->id], [
            'provider_type_id' => $input['provider_type_id'] ?? null,
            'transaction_type_id' => $input['transaction_type_id'] ?? '01',
            'special_tax_regime_id' => $input['special_tax_regime_id'] ?? null,
            'catalog_snapshot' => $snapshot, 'third_party' => $input['third_party'] ?? null,
            'conditional_data' => $input['conditional_data'] ?? null,
        ]);
        $document->emission()->firstOrCreate(['document_id' => $document->id], [
            'fiscal_environment' => $document->fiscal_environment, 'operation_key' => (string) Str::uuid(),
        ]);
    }

    public static function applySettlements(Document $document,array $input): void
    {
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
        if ($document->fresh()->balance < -0.005) FiscalAmounts::error('document','Las aplicaciones superan el saldo del documento.');
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
        if ($taxedDiscount > $taxedOriginal || $exemptDiscount > $exemptOriginal) FiscalAmounts::error('discounts','El descuento supera su base imponible.');
        $taxed = $exempt = $iva = 0;
        foreach ($items->groupBy(fn ($line) => ($line->iva_rate['code'] ?? '').':'.$line->percentage_igv) as $group) {
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
        $values = ['taxed'=>$document->total_taxed,'exempt'=>$document->total_exonerated,
            'discount'=>$document->total_discount,'charge'=>$document->total_charge,
            'iva'=>$document->total_igv,'other_taxes'=>$document->total_other_taxes,'total'=>$document->total];
        foreach ($values as &$v) $v = FiscalAmounts::convert($v,$document->currency_type_id,'VES',$document->exchange_rate_sale);
        unset($v);
        $document->currency_totals()->updateOrCreate(['document_id'=>$document->id],$values + ['currency_type_id'=>'VES','tax_breakdown'=>$breakdown]);
    }

    public static function payment(Document $document, array $input, bool $initial = false): DocumentPayment
    {
        return $document->getConnection()->transaction(function () use ($document, $input, $initial) {
            $company = Company::query()->lockForUpdate()->firstOrFail();
            $document = Document::query()->lockForUpdate()->findOrFail($document->id);
            if ($document->isVoidedOrRejected() || $document->document_type_id !== '01') FiscalAmounts::error('document','Sólo se cobran facturas vigentes.');
            $key = $input['operation_key'] ?? (string) Str::uuid();
            if (!Str::isUuid($key)) FiscalAmounts::error('operation_key', 'Use un UUID válido.');
            $existing = DocumentPayment::where('operation_key', $key)->first();
            $storedRate = $existing ? \App\Models\Tenant\DocumentTax::where('document_payment_id',$existing->id)->value('percentage') : null;
            $values = FiscalAmounts::payment($input, $document->currency_type_id, $document->exchange_rate_sale,
                $existing ? $existing->igtf_status === 'subject' : (bool)$company->igtf_enabled, $existing ? $storedRate : $company->igtf_rate);
            if ($existing) {
                if ($existing->document_id !== $document->id || $existing->reversed_at ||
                    (float) $existing->original_amount !== $values['original_amount'] || $existing->currency_type_id !== $values['currency_type_id'] ||
                    (float)$existing->exchange_rate !== $values['exchange_rate'] || $existing->exemption_reason !== $values['exemption_reason'] ||
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
            $method = PaymentMethodType::findOrFail($input['payment_method_type_id']);
            if (!$initial && $values['payment'] > $document->balance + 0.005) FiscalAmounts::error('payment', 'El pago supera el saldo disponible.');
            if ($initial && $document->emission()->where('status', '!=', 'not_requested')->exists()) FiscalAmounts::error('emission','No se modifican los pagos de una operación preparada.');
            if (!empty($input['id'])) FiscalAmounts::error('id', 'Revierta el pago y registre uno nuevo; no se editan pagos contabilizados.');
            validator($input, ['date_of_payment'=>'required|date','exchange_rate_source'=>'nullable|string|max:255','exchange_rate_date'=>'nullable|date','exemption_reason'=>'nullable|string|max:255'])->validate();
            $fields = array_intersect_key($input, array_flip(['date_of_payment','payment_method_type_id','has_card','card_brand_id','reference','payment_received','change']));

            $payment = $document->payments()->create($values + $fields + [
                'operation_key' => $key, 'payment_method_snapshot' => $method->only(['id','description','hka_code'])]);
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
            'group_id' => '01', 'document_type_id' => '08', 'series' => $series->number, 'number' => '#',
            'date_of_issue' => $payment->date_of_payment->format('Y-m-d'), 'time_of_issue' => now()->format('H:i:s'),
            'state_type_id' => '01', 'total' => 0, 'total_value' => 0, 'total_igv' => 0,
            'additional_information' => 'IGTF del pago '.$payment->operation_key]);
        $note->save();
        $note->issuer = $invoice->issuer; $note->save();
        $note->note()->create(['note_type' => 'debit', 'note_debit_type_id' => 'IGTF', 'note_description' => 'IGTF sobre pago posterior',
            'affected_document_id' => $invoice->id, 'data_affected_document' => array_merge($invoice->only(['series','number','date_of_issue','total','control_number']), ['document_type_id'=>'01'])]);
        self::fiscalData($note, ['transaction_type_id' => '98']);
        return $note;
    }

    public static function retention(Document $document, array $input)
    {
        return $document->getConnection()->transaction(function () use ($document, $input) {
            Company::query()->lockForUpdate()->firstOrFail();
            $document = Document::query()->lockForUpdate()->findOrFail($document->id);
            $data = validator($input, [
                'tax_kind'=>'required|in:IVA,ISLR', 'voucher_number'=>'required|string|max:100', 'voucher_date'=>'required|date',
                'agent_id'=>'required|integer', 'concept_id'=>'nullable|string|max:4', 'base'=>'required|numeric|gt:0',
                'percentage'=>'required|numeric|gt:0|max:100', 'subtrahend'=>'nullable|numeric|min:0',
                'amount'=>'required|numeric|gt:0', 'currency_type_id'=>'required|in:VES,USD', 'exchange_rate'=>'required|numeric|gt:0',
                'attachment'=>'nullable|string|max:255',
            ])->validate();
            if ($document->received_retentions()->where('tax_kind',$data['tax_kind'])->where('agent_id',$data['agent_id'])->where('voucher_number',$data['voucher_number'])->exists()) FiscalAmounts::error('voucher_number','El comprobante ya está registrado.');
            foreach (['base','percentage','subtrahend','amount'] as $field) $data[$field] = round((float)($data[$field] ?? 0),2);
            $data['exchange_rate'] = round((float)$data['exchange_rate'],3);
            if ($data['amount']<=0 || $data['base']<=0 || $data['exchange_rate']<=0) FiscalAmounts::error('amount','Importes y tasa deben ser positivos con la precisión del sistema.');
            $agent = Person::findOrFail($data['agent_id']);
            if ($agent->id !== $document->customer_id) FiscalAmounts::error('agent_id', 'El agente debe ser el cliente de la factura.');
            if ($data['tax_kind'] === 'ISLR' && empty($data['concept_id'])) FiscalAmounts::error('concept_id', 'Indique el concepto ISLR.');
            if (!empty($data['concept_id']) && !DB::connection('tenant')->table('cat_retention_concept')->where('id',$data['concept_id'])->exists()) FiscalAmounts::error('concept_id', 'Concepto ISLR inválido.');
            $expected = round(max(0, $data['base'] * $data['percentage'] / 100 - ($data['subtrahend'] ?? 0)), 2);
            if (abs($data['amount'] - $expected) > 0.01) FiscalAmounts::error('amount','El importe no coincide con base, porcentaje y sustraendo.');
            $applied = FiscalAmounts::convert($data['amount'], $data['currency_type_id'], $document->currency_type_id, $data['exchange_rate']);
            if ($document->document_type_id !== '01' || $document->isVoidedOrRejected()) FiscalAmounts::error('document','Sólo se retiene una factura vigente.');
            if ($applied > $document->balance + 0.005) FiscalAmounts::error('amount','La retención supera el saldo disponible.');
            if ($data['tax_kind']==='IVA') {
                $alreadyRetained = $document->received_retentions()->where('tax_kind','IVA')->sum('applied_amount');
                $available = FiscalAmounts::convert($document->total_igv - $alreadyRetained, $document->currency_type_id, $data['currency_type_id'], $data['exchange_rate']);
                if ($data['base'] > FiscalAmounts::convert($document->total_igv, $document->currency_type_id, $data['currency_type_id'], $data['exchange_rate']) + 0.01 || $data['amount'] > $available + 0.01) FiscalAmounts::error('base','La base de retención IVA supera el IVA de la factura.');
            }
            if (!empty($data['attachment'])) {
                $prefix = 'received_retentions/'.$document->id.'/';
                if (!str_starts_with($data['attachment'], $prefix) || str_contains($data['attachment'],'..') || !\Storage::disk('tenant')->exists($data['attachment'])) FiscalAmounts::error('attachment','Adjunto inválido para esta factura y tenant.');
            }
            $retention=$document->received_retentions()->create($data + ['agent' => $agent->only(['number','name','identity_document_type_id','address']), 'agent_number'=>$agent->number,'applied_amount'=>$applied]);
            self::updateSettlementState($document);
            return $retention;
        });
    }

    public static function reverse(DocumentPayment $payment, string $reason): void
    {
        $payment->getConnection()->transaction(function () use ($payment, $reason) {
            Company::query()->lockForUpdate()->firstOrFail();
            $document = Document::query()->lockForUpdate()->findOrFail($payment->document_id);
            $payment = DocumentPayment::query()->lockForUpdate()->findOrFail($payment->id);
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

    private static function updateSettlementState(Document $document): void
    {
        $document=$document->fresh();
        $document->total_canceled=$document->balance<=0.005;
        if ($document->isDirty('total_canceled')) $document->save();
    }
}
// ######## FIN PERSISTENCIA FISCAL VENEZUELA ########
