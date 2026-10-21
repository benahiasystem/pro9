<?php

namespace App\Support\Exact;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

// ######## INICIO CALCULOS EXACTOS PRO9 ########
/** Common exact receivables for screens, dashboards, accounting and exports. */
final class Receivables
{
    public const EXPORT_FIELDS = ['total','total_payable','total_payment','total_credit_notes','retention_amount',
        'guarantee_amount','refund_amount','total_to_pay','exact_balance','settlement_adjustment',
        'total_ves_equivalent','balance_ves_equivalent','arrears'];
    public static function dataset(array $filters, bool $all = false): ReportDataset
    {
        if (isset($filters['source_type']) && !in_array($filters['source_type'],['document','sale_note'],true)) {
            throw new \InvalidArgumentException('Origen de cuenta por cobrar no soportado.');
        }
        $db = DB::connection('tenant');
        $user = auth()->user();
        if (!$user) throw new \Illuminate\Auth\AuthenticationException();
        return ReadSnapshot::run($db, function () use ($db, $filters, $all, $user) {
            $raw = $db->table('configurations')->value('finances');
            $settings = $raw ? JsonDecimals::decode($raw) : ['apply_arrears'=>false, 'arrears_amount'=>'0'];
            return new ReportDataset((function () use ($db, $filters, $all, $user, $settings) {
                foreach (['document'=>['documents','document_items','document_id'], 'sale_note'=>['sale_notes','sale_note_items','sale_note_id']] as $type=>[$table,$lineTable,$foreign]) {
                    if (isset($filters['source_type']) && $filters['source_type'] !== $type) continue;
                    $query = $db->table($table)->join('persons','persons.id','=',$table.'.customer_id')
                        ->join('users','users.id','=',$table.'.user_id')
                        ->whereIn($table.'.state_type_id',['01','03','05','07','13'])
                        ->select(array_map(fn($field)=>$table.'.'.$field, ['id','customer_id','user_id','series','number','total',
                            'total_payable','calculation_snapshot','currency_type_id','exchange_rate_sale','date_of_issue','purchase_order']))
                        ->addSelect('persons.name as customer_name','users.name as username');
                    if ($type === 'document') $query->leftJoin('invoices','invoices.document_id','=','documents.id')
                        ->whereIn('documents.document_type_id',['01','08'])->addSelect('invoices.date_of_due as due_at',
                            'documents.document_type_id','documents.control_number','documents.fiscal_emission_mode');
                    else $query->where('sale_notes.changed',false)->where('sale_notes.total_canceled',false)->addSelect('sale_notes.due_date as due_at');
                    if (!empty($filters['customer_id'])) $query->where($table.'.customer_id',$filters['customer_id']);
                    if (!$all) {
                        if (empty($filters['stablishmentUnpaidAll'])) $query->where($table.'.establishment_id', $filters['establishment_id'] ?? $user->establishment_id);
                        if ($user->type === 'seller') $query->where($table.'.user_id',$user->id);
                        elseif ($user->type === 'admin' && !empty($filters['user_id'])) $query->where($table.'.user_id',$filters['user_id']);
                        foreach (['payment_method_type_id'] as $field) if (!empty($filters[$field])) $query->where($table.'.'.$field,$filters[$field]);
                        if (isset($filters['purchase_order'])) $query->where($table.'.purchase_order',$filters['purchase_order']);
                        [$start,$end] = self::dates($filters);
                        $due = $type === 'document' ? 'invoices.date_of_due' : 'sale_notes.due_date';
                        if ($start !== null) $query->whereBetween(($filters['date_type'] ?? '') === 'due' ? $due : $table.'.date_of_issue', [$start,$end]);
                        if (($filters['estado'] ?? '') === 'vigente') $query->whereDate($due,'>=',Carbon::now()->toDateString());
                        elseif (($filters['estado'] ?? '') === 'vencido') $query->whereDate($due,'<',Carbon::now()->toDateString());
                        if (!empty($filters['web_platform_id'])) $query->whereExists(function ($q) use ($lineTable,$foreign,$table,$filters) {
                            $q->selectRaw('1')->from($lineTable)->join('items','items.id','=',$lineTable.'.item_id')
                                ->whereColumn($lineTable.'.'.$foreign,$table.'.id')->where('items.web_platform_id',$filters['web_platform_id']);
                        });
                    }
                    $last = 0;
                    do {
                        $batch = (clone $query)->where($table.'.id','>',$last)->orderBy($table.'.id')->limit(500)->get();
                        if ($batch->isEmpty()) break;
                        $ids = $batch->pluck('id')->all();
                        [$payments,$paymentDates] = self::payments($db,$type,$ids);
                        $credits = $retentions = $funds = $adjustments = $refunds = $guides = [];
                        if ($type === 'document') {
                            $creditsQuery = $db->table('documents as nc')->join('notes','notes.document_id','=','nc.id')
                                ->whereIn('notes.affected_document_id',$ids)->where('nc.document_type_id','07')
                                ->whereNotIn('nc.state_type_id',['09','11'])->select('nc.*','notes.affected_document_id')->distinct();
                            foreach ($creditsQuery->lazyById(500,'nc.id','id') as $row) {
                                $id = $row->affected_document_id;
                                $value = isset($row->total_payable) ? Decimal::readRequired($row,'total_payable') : Decimal::readRequired($row,'total');
                                $credits[$id] = ($credits[$id] ?? Decimal::of(0))->plus($value)->simplified();
                            }
                            $retentions = self::sums($db->table('document_received_retentions')->whereIn('document_id',$ids),'document_id','applied_amount');
                            $funds = self::sums($db->table('document_guarantee_funds')->whereIn('document_id',$ids),'document_id','amount');
                            $adjustments = self::sums($db->table('document_note_adjustments')->whereIn('affected_document_id',$ids)->where('kind','fund')->where('status','applied'),'affected_document_id','amount');
                            $refunds = self::sums($db->table('document_note_refunds')->whereIn('affected_document_id',$ids)->where('component','principal')->whereNull('reversed_at'),'affected_document_id','amount');
                            foreach ($db->table('dispatches')->whereIn('reference_document_id',$ids)->lazyById(500) as $guide) {
                                $guides[$guide->reference_document_id][] = ['id'=>$guide->id,'external_id'=>$guide->external_id,
                                    'number'=>\App\Services\Fiscal\FiscalIdentity::numberFull($guide->series,$guide->number),
                                    'date_of_issue'=>Carbon::parse($guide->date_of_issue)->format('Y-m-d'),
                                    'date_of_shipping'=>Carbon::parse($guide->date_of_shipping)->format('Y-m-d'),
                                    'download_external_pdf'=>route('tenant.download.external_id',['model'=>'dispatch','type'=>'pdf','external_id'=>$guide->external_id])];
                            }
                        }
                        $platforms = [];
                        $platformQuery = $db->table($lineTable)->join('items','items.id','=',$lineTable.'.item_id')
                            ->join('web_platforms','web_platforms.id','=','items.web_platform_id')->whereIn($lineTable.'.'.$foreign,$ids)
                            ->select($lineTable.'.id',$lineTable.'.'.$foreign,'web_platforms.id as platform_id','web_platforms.name');
                        foreach ($platformQuery->lazyById(500,$lineTable.'.id','id') as $platform) {
                            $platforms[$platform->$foreign][$platform->platform_id] = ['id'=>$platform->platform_id,'name'=>$platform->name];
                        }
                        foreach ($batch as $row) {
                            $last = $row->id;
                            $id = $row->id;
                            $zero = Decimal::of(0);
                            $fund = Decimal::max(0,($funds[$id] ?? $zero)->minus($adjustments[$id] ?? $zero));
                            $dto = self::row($row,$type,$payments[$id] ?? $zero,$credits[$id] ?? $zero,
                                $retentions[$id] ?? $zero,$fund,$refunds[$id] ?? $zero,$paymentDates[$id] ?? null,$settings);
                            if (!Decimal::readRequired($dto,'total_to_pay')->isPositive()) continue;
                            $dto['guides'] = $guides[$id] ?? [];
                            $dto['web_platforms'] = array_values($platforms[$id] ?? []);
                            yield $dto;
                        }
                    } while (count($batch) === 500);
                }
            })());
        });
    }

    public static function row($row, string $type, $paid, $credits, $retentions, $fund, $refunds, ?string $lastPayment, array $settings = []): array
    {
        $total = Decimal::readRequired($row,'total');
        $payable = isset($row->total_payable) ? Decimal::readRequired($row,'total_payable') : $total;
        if ($type !== 'document' || $row->document_type_id !== '01') { $credits = Decimal::of(0); $refunds = Decimal::of(0); }
        $balance = $payable->minus($credits)->minus($paid)->minus($retentions)->minus($fund)->plus($refunds)->simplified();
        $due = !empty($row->due_at) ? Carbon::parse($row->due_at) : null;
        $delay = $balance->isPositive() && $due && Carbon::now()->greaterThan($due) ? Carbon::now()->diffInDays($due) : 0;
        $arrears = !empty($settings['apply_arrears']) ? Decimal::of(Decimal::input($settings['arrears_amount'],'finances.arrears_amount'))->multipliedBy((string)$delay) : Decimal::of(0);
        $values = ['total'=>$total,'total_payable'=>$payable,'total_payment'=>Decimal::of($paid),'total_credit_notes'=>Decimal::of($credits),
            'retention_amount'=>Decimal::of($retentions),'guarantee_amount'=>Decimal::of($fund),'refund_amount'=>Decimal::of($refunds),
            'total_to_pay'=>$balance,'total_subtraction'=>$balance,'exact_balance'=>$balance->plus($total)->minus($payable),
            'settlement_adjustment'=>$payable->minus($total),'arrears'=>$arrears];
        $values = array_merge($values,OutstandingSummary::equivalentValues($total,$balance,$row->currency_type_id,$row->exchange_rate_sale));
        $identity = \App\Services\Fiscal\FiscalIdentity::forDocument((new \App\Models\Tenant\Document())->setRawAttributes((array)$row));
        return array_merge([
            'id'=>$row->id,'report_key'=>$type.':'.$row->id,'type'=>$type,
            'document_type_id'=>$type === 'document' ? $row->document_type_id : null,
            'date_of_issue'=>Carbon::parse($row->date_of_issue)->format('d-m-Y'),'date_of_due'=>$due ? $due->format('d/m/Y') : null,
            'due_date_iso'=>$due ? $due->format('Y-m-d') : null,
            'date_payment_last'=>$lastPayment,'delay_payment'=>$delay,'customer_id'=>$row->customer_id,'customer_name'=>$row->customer_name,
            'difference_days'=>$due ? Carbon::parse($row->date_of_issue)->diffInDays($due) : null,
            'number_full'=>$identity['number_full'],'fiscal_identity'=>$type === 'document' ? $identity : null,
            'user_id'=>$row->user_id,'username'=>$row->username,'purchase_order'=>$row->purchase_order ?? null,
            'currency_type_id'=>$row->currency_type_id,'exchange_rate_sale'=>$row->exchange_rate_sale,
            'calculation_snapshot'=>Decimal::snapshot($values),
        ],Decimal::values($values));
    }

    private static function sums($query, string $foreign, string $field): array
    {
        $sums = [];
        foreach ($query->lazyById(500) as $row) {
            $id = $row->$foreign;
            $sums[$id] = ($sums[$id] ?? Decimal::of(0))->plus(Decimal::readRequired($row,$field))->simplified();
        }
        return $sums;
    }

    private static function payments($db, string $type, array $ids): array
    {
        $table = $type === 'document' ? 'document_payments' : 'sale_note_payments';
        $foreign = $type === 'document' ? 'document_id' : 'sale_note_id';
        $query = $db->table($table)->whereIn($foreign,$ids);
        if ($type === 'document') $query->whereNull('reversed_at');
        $sums = $dates = [];
        foreach ($query->lazyById(500) as $row) {
            $id = $row->$foreign;
            $sums[$id] = ($sums[$id] ?? Decimal::of(0))->plus(Decimal::readRequired($row,'payment'))->simplified();
            if (!isset($dates[$id]) || strcmp((string)$row->date_of_payment,$dates[$id]) > 0) $dates[$id] = (string)$row->date_of_payment;
        }
        return [$sums,$dates];
    }

    private static function dates(array $filters): array
    {
        switch ($filters['period'] ?? null) {
            case 'month': return [Carbon::parse($filters['month_start'].'-01')->format('Y-m-d'),Carbon::parse($filters['month_start'].'-01')->endOfMonth()->format('Y-m-d')];
            case 'between_months': return [Carbon::parse($filters['month_start'].'-01')->format('Y-m-d'),Carbon::parse($filters['month_end'].'-01')->endOfMonth()->format('Y-m-d')];
            case 'date': return [$filters['date_start'],$filters['date_start']];
            case 'between_dates':
            case 'last_week': return [$filters['date_start'],$filters['date_end']];
            default: return [null,null];
        }
    }
}
// ######## FIN CALCULOS EXACTOS PRO9 ########
