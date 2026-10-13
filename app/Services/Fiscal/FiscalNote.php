<?php
namespace App\Services\Fiscal;

use App\Models\Tenant\{Company, Document, DocumentItem, DocumentNoteRefund, Note};
use App\Services\ExchangeRates\ExchangeRateMath as Math;
use App\Http\Controllers\Tenant\DocumentFiscalController;
use Illuminate\Support\Facades\DB;

/** Server-owned note semantics. Called inside the common commercial transaction. */
final class FiscalNote
{
    public const CREDIT = ['01'=>'03', '07'=>'04', '04'=>'04', '09'=>'04'];
    public const DEBIT = ['02'=>'02', '01'=>'02', '03'=>'02'];

    public static function ordinary($d): bool
    {
        return $d instanceof Document && in_array($d->document_type_id, ['07','08'], true) && !empty($d->note->creation_key);
    }

    public static function digital(Document $d): bool
    {
        return self::ordinary($d) && $d->fiscal_emission_mode === 'digital';
    }

    public static function reason(Document $invoice): ?string
    {
        if ($invoice->document_type_id !== '01' || $invoice->isVoidedOrRejected()) return 'La nota requiere una Factura vigente.';
        if (\App\Services\ExportInvoicePolicy::isExport($invoice->invoice->operation_type_id ?? '')) return 'Las notas de exportación todavía no están habilitadas.';
        if ($invoice->fiscal_emission_mode === 'digital' && ($invoice->fiscal_environment !== 'demo'
            || optional($invoice->emission)->status !== 'confirmed' || !$invoice->control_number
            || $invoice->control_number !== optional($invoice->emission)->control_number)) return 'Confirme la factura en HKA y obtenga su control real antes de crear una nota.';
        return null;
    }

    public static function activeCredits(Document $invoice)
    {
        return Document::where('document_type_id','07')->whereNotIn('state_type_id', Document::VOIDED_REJECTED_IDS)
            ->whereHas('note', fn ($q) => $q->where('affected_document_id',$invoice->id));
    }

    public static function remaining(Document $invoice,string $field): float
    {
        if (!$invoice->exists) return (float)$invoice->$field;
        return max(0,round($invoice->$field-self::activeCredits($invoice)->sum($field),2));
    }

    public static function credits(Document $invoice): float { return round(self::activeCredits($invoice)->sum('total'),2); }

    public static function hasRelatedNotes(Document $invoice): bool
    {
        return $invoice->exists && $invoice->document_type_id === '01' && Note::where('affected_document_id',$invoice->id)->whereNotNull('creation_key')->exists();
    }

    public static function collectionDocumentIds(Document $invoice): array
    {
        return array_merge([$invoice->id],Document::where('document_type_id','08')->whereNotIn('state_type_id',Document::VOIDED_REJECTED_IDS)
            ->whereHas('note',fn ($q)=>$q->where('affected_document_id',$invoice->id)->whereNotNull('creation_key'))->pluck('id')->all());
    }

    public static function refunded(Document $invoice): float
    {
        return round(DocumentNoteRefund::where('affected_document_id',$invoice->id)->where('component','principal')->whereNull('reversed_at')->sum('amount'),2);
    }

    /** Exact cumulative rounding distributes all cents once, including global discounts/charges. */
    public static function distribute($amount, array $weights): array
    {
        $total = array_sum($weights); $sum = 0; $prior = 0; $result = [];
        foreach ($weights as $key=>$weight) {
            $sum += $total ? $weight : 1;
            $current = Math::finalAmount(Math::rational($amount)->multipliedBy($sum)->dividedBy($total ?: count($weights)));
            $result[$key] = Math::finalAmount(Math::rational($current)->minus($prior)); $prior = $current;
        }
        return $result;
    }

    /** Entitlements use the original fiscal groups, never the current product price or IVA. */
    public static function entitlements(Document $invoice): array
    {
        $lines = $invoice->items; $result = []; $groups = [];
        foreach ($lines as $line) {
            $key = ($line->iva_rate['code'] ?? '').':'.$line->percentage_igv;
            $groups[$key][$line->id] = (float)$line->total_base_igv;
            $result[$line->id] = ['line'=>$line, 'base'=>0, 'iva'=>0, 'value'=>0];
        }
        foreach ($groups as $key=>$weights) {
            $tax = $invoice->taxes->first(fn ($t) => $t->tax_kind === 'IVA' && ($t->code.':'.$t->percentage) === $key);
            // DECIMAL strings may have trailing zeros; compare numerically within a fiscal code.
            if (!$tax) {
                $first = $result[array_key_first($weights)]['line'];
                $tax = $invoice->taxes->first(fn ($t) => $t->tax_kind === 'IVA' && $t->code === ($first->iva_rate['code'] ?? '') && (float)$t->percentage === (float)$first->percentage_igv);
            }
            $base = self::distribute($tax ? $tax->base : array_sum($weights), $weights);
            $iva = self::distribute($tax ? $tax->amount : $lines->whereIn('id',array_keys($weights))->sum('total_igv'), $weights);
            foreach ($weights as $id=>$unused) { $result[$id]['base']=$base[$id]; $result[$id]['iva']=$iva[$id]; }
        }
        $value = self::distribute($invoice->total_value, $lines->mapWithKeys(fn ($l)=>[$l->id=>(float)$l->total_value])->all());
        foreach ($result as $id=>&$row) $row['value'] = $value[$id];
        unset($row);
        $creditIds = self::activeCredits($invoice)->pluck('id');
        $credited = DocumentItem::whereIn('document_id',$creditIds)->get()->groupBy('affected_document_item_id');
        foreach ($result as $id=>&$row) {
            $used = $credited->get($id, collect());
            $row['remaining_quantity'] = max(0, (float)$row['line']->quantity - $used->sum('inventory_quantity'));
            foreach (['base'=>'total_base_igv','iva'=>'total_igv','value'=>'total_value'] as $k=>$field) $row['remaining_'.$k] = max(0, round($row[$k]-$used->sum($field),2));
            $row['remaining_total'] = round($row['remaining_value']+$row['remaining_iva'],2);
        }
        unset($row);
        return $result;
    }

    public static function context(Document $invoice): array
    {
        $reason = self::reason($invoice);
        return ['can_create'=>$reason === null,'block_reason'=>$reason,'control_number'=>$invoice->control_number,
            'credit_available'=>max(0, round($invoice->total - $invoice->taxes->where('tax_kind','IGTF')->sum('amount') - self::credits($invoice),2)),
            'items'=>array_values(array_map(function ($r) {
                $allocations=FiscalNoteInventory::snapshot($r['line'],$r['remaining_quantity'],[]);
                return ['id'=>$r['line']->id, 'description'=>$r['line']->item->description,
                    'quantity'=>$r['line']->quantity,'remaining_quantity'=>$r['remaining_quantity'], 'remaining_total'=>$r['remaining_total'],
                    'iva_rate'=>$r['line']->iva_rate, 'unit_type_id'=>$r['line']->item->unit_type_id,
                    'serials'=>array_map(fn ($s)=>['id'=>$s['id'],'series'=>$s['series']],$allocations['lots'] ?? []),
                    'lots'=>array_map(fn ($l)=>['id'=>$l['id'],'quantity'=>$l['compromise_quantity']],$allocations['IdLoteSelected'] ?? [])];
            }, self::entitlements($invoice))),
            'notes'=>Note::where('affected_document_id',$invoice->id)->get()->map(fn ($n)=>[
                'id'=>$n->document_id,'number'=>$n->document->number_full,'type'=>$n->note_type,'total'=>$n->document->total,
                'fiscal_status'=>$n->document->emission->status ?? null])->all()];
    }

    public static function hash(array $input): string
    {
        $fields = ['document_type_id','series','number','customer_id','establishment_id','currency_type_id','note','note_creation_key',
            'date_of_issue','time_of_issue','exchange_rate_sale','exchange_rate_source','exchange_rate_date','items','payments','payment_condition_id'];
        $data = array_intersect_key($input, array_flip($fields));
        // Product descriptions and posted totals are not authority, but binding them detects key reuse.
        $sort = function (&$value) use (&$sort) { if (!is_array($value)) return; foreach ($value as &$v) $sort($v); unset($v); if (!array_is_list($value)) ksort($value); };
        $sort($data);
        return hash('sha256',json_encode($data, JSON_THROW_ON_ERROR));
    }

    public static function existing(array $input): ?Document
    {
        if (empty($input['note_creation_key'])) return null;
        $note = Note::where('creation_key',$input['note_creation_key'])->lockForUpdate()->first();
        if (!$note) return null;
        DocumentFiscalController::authorizeDocument($note->document);
        if ($note->request_hash !== self::hash($input)) abort(409, 'La clave de creación ya pertenece a otra nota o datos distintos.');
        return $note->document;
    }

    public static function resolve(array $input): array
    {
        $company=Company::query()->lockForUpdate()->firstOrFail();
        $invoice = Document::query()->lockForUpdate()->findOrFail($input['note']['affected_document_id'] ?? 0);
        DocumentFiscalController::authorizeDocument($invoice);
        NoteAnnulment::assertNotBusy($invoice);
        if ($company->fiscal_emission_mode !== $invoice->fiscal_emission_mode || $company->fiscal_environment !== $invoice->fiscal_environment) FiscalAmounts::error('note','La configuración debe corresponder a la modalidad y ambiente de la factura original.');
        if ($reason=self::reason($invoice)) FiscalAmounts::error('note',$reason);
        validator($input,['note_creation_key'=>'required|uuid','note.note_description'=>'required|string|max:255',
            'date_of_issue'=>'required|date|after_or_equal:'.$invoice->date_of_issue->format('Y-m-d')])->validate();
        if (!empty($input['note']['data_affected_document'])) FiscalAmounts::error('note','Seleccione una factura registrada; la referencia fiscal la determina el servidor.');
        foreach (['customer_id','establishment_id','currency_type_id'] as $field) if ((string)($input[$field] ?? '') !== (string)$invoice->$field) FiscalAmounts::error($field,'Conserve el cliente, sucursal y moneda de la factura afectada.');
        $credit = $input['document_type_id'] === '07';
        if (!$credit) {
            $input['payment_condition_id']=$input['payment_condition_id'] ?? '02';
            validator($input,['payment_condition_id'=>'required|in:01,02'])->validate();
            if (!empty($input['payments'])) abort_unless(auth()->user()->type === 'admin' || auth()->user()->create_payment,403);
            foreach ($input['payments'] ?? [] as $payment) {
                validator($payment,['payment_destination_id'=>'required','operation_key'=>'required|uuid'])->validate();
                $method=\App\Models\Tenant\PaymentMethodType::where('is_active',1)->find($payment['payment_method_type_id'] ?? '');
                if (!$method || $method->is_credit) FiscalAmounts::error('payment_method_type_id','Seleccione un medio de cobro real activo.');
                $currency=$payment['currency_type_id'] ?? $invoice->currency_type_id;
                if ($payment['payment_destination_id'] === 'cash') {
                    $cash=\App\Models\Tenant\Cash::where('user_id',auth()->id())->where('state',true)->lockForUpdate()->first();
                    if (!$cash || $cash->user->establishment_id != $invoice->establishment_id) FiscalAmounts::error('payment_destination_id','Seleccione una caja propia abierta de la sucursal.');
                } else {
                    $bank=\App\Models\Tenant\BankAccount::query()->lockForUpdate()->find($payment['payment_destination_id']);
                    if (!$bank || $bank->establishment_id != $invoice->establishment_id || $bank->currency_type_id !== $currency) FiscalAmounts::error('payment_destination_id','La cuenta debe corresponder a la sucursal y moneda recibida.');
                }
            }

        }
        $reasonId = $input['note'][$credit ? 'note_credit_type_id':'note_debit_type_id'] ?? null;
        $reasons = $credit ? self::CREDIT : self::DEBIT;
        $table = $credit ? 'cat_note_credit_types':'cat_note_debit_types';
        $catalog = DB::connection('tenant')->table($table)->where('id',$reasonId)->where('active',1)->first();
        if (!isset($reasons[$reasonId]) || !$catalog) FiscalAmounts::error('note','Seleccione un motivo vigente para este tipo de nota.');
        if (!empty($input['taxes']) || !empty($input['received_retentions']) || !empty($input['guarantee_fund']) || !empty($input['third_party_id']) || !empty($input['fiscal_data'])) FiscalAmounts::error('note','Los ajustes relacionados y la clasificación se gestionan desde la nota guardada.');
        $hash = self::hash($input);
        $input['note'] = ['note_type'=>$credit ? 'credit':'debit', 'affected_document_id'=>$invoice->id,
            'note_credit_type_id'=>$credit ? $reasonId:null, 'note_debit_type_id'=>$credit ? null:$reasonId,
            'note_description'=>$input['note']['note_description'], 'creation_key'=>$input['note_creation_key'], 'request_hash'=>$hash,
            'effect_snapshot'=>['reason'=>(array)$catalog,'transaction_type_id'=>$reasons[$reasonId],'version'=>1]];
        foreach (['customer_id','establishment_id','currency_type_id'] as $field) $input[$field]=$invoice->$field;
        $input['fiscal_environment']=$invoice->fiscal_environment;
        $input['customer']=$invoice->customer; $input['establishment']=$invoice->establishment;
        if ($credit) {
            $input['exchange_rate_sale']=$invoice->exchange_rate_sale;
            $input['exchange_rate_source']=$invoice->exchange_rate_source;
            $input['exchange_rate_date']=$invoice->exchange_rate_date;
            if (!empty($input['payments'])) FiscalAmounts::error('payments','Una NC no registra cobros. Utilice Registrar devolución después de confirmar HKA.');
            $input['payment_condition_id']=null;
        }
        $entitlements = self::entitlements($invoice);
        $posted = $input['items'] ?? [];
        if ($credit && $reasonId === '01') $posted = array_map(fn ($r)=>['affected_document_item_id'=>$r['line']->id,
            'quantity'=>$r['remaining_quantity'] ?: 1,'adjustment_amount'=>$r['remaining_total']], array_filter($entitlements,fn ($r)=>$r['remaining_total'] > 0 || $r['remaining_quantity'] > 0));
        if (!$posted) FiscalAmounts::error('items','Añada al menos una partida con un ajuste positivo.');
        $seen=[]; $rows=[];
        foreach ($posted as $index=>$row) {
            $sourceId = $row['affected_document_item_id'] ?? null;
            if ($sourceId) {
                if (!isset($entitlements[$sourceId]) || isset($seen[$sourceId])) FiscalAmounts::error("items.$index",'La partida no pertenece a la factura o está repetida.');
                $seen[$sourceId]=true; $e=$entitlements[$sourceId]; $source=$e['line'];
                if ($credit && $reasonId === '07' && $source->item->unit_type_id === 'SERV') FiscalAmounts::error('items','La devolución de mercancía requiere un producto; el servicio se ajusta por precio o anulación.');
                $return = $credit && in_array($reasonId,['01','07'],true);
                if ($return && $reasonId === '07') validator($row,['quantity'=>'required|numeric|gt:0|regex:/^[0-9]+(?:\\.[0-9]{1,4})?$/D'])->validate();
                $quantity = $return ? ($reasonId === '01' ? $e['remaining_quantity'] : ($row['quantity'] ?? 0)) : 1;
                if ($quantity < 0 || ($return && $quantity > $e['remaining_quantity']+0.00005)) FiscalAmounts::error("items.$index.quantity",'La cantidad supera lo pendiente de devolución.');
                if ($return && $reasonId === '07' && $quantity <= 0) FiscalAmounts::error("items.$index.quantity",'Indique una cantidad positiva.');
                if ($return) {
                    $ratio = $e['remaining_quantity'] ? Math::rational($quantity)->dividedBy($e['remaining_quantity']) : Math::rational(1);
                    $base=Math::finalAmount($ratio->multipliedBy($e['remaining_base']));
                    $iva=Math::finalAmount($ratio->multipliedBy($e['remaining_iva']));
                    $value=Math::finalAmount($ratio->multipliedBy($e['remaining_value']));
                } else {
                    $amount=$row['adjustment_amount'] ?? $row['total'] ?? 0;
                    validator(['amount'=>$amount],['amount'=>'required|numeric|gt:0'])->validate();
                    $amount=Math::finalAmount($amount);
                    if ($amount <= 0) FiscalAmounts::error('items.adjustment_amount','El importe debe ser positivo a dos decimales.');
                    if ($credit && $amount > $e['remaining_total']+0.005) FiscalAmounts::error("items.$index.adjustment_amount",'El ajuste supera el importe acreditable de la partida.');
                    $rate=$source->percentage_igv;
                    if ($credit) {
                        $ratio=Math::rational($amount)->dividedBy($e['remaining_total']);
                        $base=Math::finalAmount($ratio->multipliedBy($e['remaining_base']));
                        $value=Math::finalAmount($ratio->multipliedBy($e['remaining_value']));
                        $iva=Math::finalAmount(Math::rational($amount)->minus($value));
                    } else {
                        $base=Math::finalAmount(Math::rational($amount)->dividedBy(Math::rational(1)->plus(Math::rational($rate)->dividedBy(100))));
                        $iva=Math::finalAmount(Math::rational($amount)->minus($base)); $value=$base;
                    }
                    if ($credit && ($base > $e['remaining_base']+0.005 || $iva > $e['remaining_iva']+0.005)) FiscalAmounts::error("items.$index",'El ajuste supera la base o IVA disponible.');
                }
                $qty=$quantity ?: 1;
                $line=$source->toArray(); $line['additional_information']=$source->getRawOriginal('additional_information'); unset($line['id'],$line['document_id'],$line['affectation_igv_type'],$line['price_type']);
                $line['affected_document_item_id']=$sourceId; $line['inventory_quantity']=$return ? $quantity : 0;
                $line['quantity']=$qty; $line['discounts']=[]; $line['charges']=[];
                $line['unit_value']=Math::divide($base,$qty,6); $line['unit_price']=Math::divide($value+$iva,$qty,6);
                $line['total_base_igv']=$base; $line['total_igv']=$iva; $line['total_value']=$value;
                $line['total_taxes']=$iva; $line['total']=$value+$iva; $line['total_discount']=0;
                $line['total_charge']=round($value-$base,2);
                $line['item']=FiscalNoteInventory::snapshot($source,$line['inventory_quantity'],$row);
                $rows[]=$line;
            } else {
                if ($credit || $reasonId === '02') FiscalAmounts::error("items.$index",'Seleccione una partida de la factura original.');
                $item=\App\Models\Tenant\Item::findOrFail($row['item_id'] ?? 0);
                if ($item->unit_type_id !== 'SERV') FiscalAmounts::error("items.$index",'Los cargos de intereses o despacho requieren un servicio.');
                $row['item']=$item->toArray(); $row['quantity']=1;
                $row['unit_price']=$row['adjustment_amount'] ?? $row['unit_price'] ?? 0;
                $row['discounts']=[]; $row['charges']=[]; $row['inventory_quantity']=0;
                $rows[]=FiscalDocumentPersistence::line($row);
            }
        }
        $input['items']=$rows; $input['discounts']=[]; $input['charges']=[]; $input['total_prepayment']=0;
        $input['total_exportation']=0; $input['fee']=[];
        $input['fiscal_data']=['transaction_type_id'=>$reasons[$reasonId]];
        $input['note']['effect_snapshot']['invoice_fiscal_data']=$invoice->fiscal_data ? $invoice->fiscal_data->only(['provider_type_id','special_tax_regime_id','catalog_snapshot','third_party','third_party_person_id']) : [];
        return $input;
    }

    public static function finish(Document $note): void
    {
        if (!self::ordinary($note)) return;
        $invoice=$note->note->affected_document;
        if ($note->total <= 0) FiscalAmounts::error('total','La nota requiere un importe positivo.');
        if ($note->document_type_id === '08' && $note->payment_condition_id === '01' && $note->fresh()->balance > 0.005) FiscalAmounts::error('payments','La ND al contado requiere cobros reales completos.');
        if ($note->document_type_id === '07' && self::credits($invoice) > $invoice->total-$invoice->taxes->where('tax_kind','IGTF')->sum('amount')+0.005) FiscalAmounts::error('total','Las NC acumuladas superan el importe comercial de la factura.');
        FiscalDocumentPersistence::updateSettlementState($invoice);
        \App\Models\Tenant\CashDocumentCredit::where('document_id',$invoice->id)->update(['status'=>$invoice->fresh()->balance <= 0.005 ? 'PROCESSED':'PENDING']);
        DocumentEmissionView::invalidate();
    }
}
