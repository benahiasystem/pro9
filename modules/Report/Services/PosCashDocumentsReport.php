<?php

namespace Modules\Report\Services;

use App\Models\Tenant\CashDocument;
use App\Models\Tenant\Document;
use App\Support\Exact\Decimal as D;
use App\Support\Exact\ReportDataset;
use App\Support\Exact\ReportProjection;
use App\Services\Fiscal\FiscalIdentity;

/** Commercial documents linked to the selected cash shifts, not cash receipts. */
final class PosCashDocumentsReport
{
    public const FIELDS=['total_taxed','total_igv','total'];

    public static function build(string $type, $userId, $viewer): array
    {
        $references=CashDocument::query()->whereHas('cash',function ($query) use ($userId,$viewer) {
            if ($viewer->type !== 'admin') $query->where('user_id',$viewer->id);
            if ($userId !== null) $query->where('user_id',$userId);
        })->where(function ($query) {
            $query->whereNotNull('document_id')->orWhereNotNull('sale_note_id');
        })->with(['document'=>function ($query) {
            $query->withoutEagerLoads()->with(['state_type','document_type','fiscal_environment_type','note.affected_document']);
        },'sale_note'=>function ($query) {
            $query->withoutEagerLoads()->with(['state_type','fiscal_environment_type']);
        }])->orderBy('id')->get();

        $cashInvoices=[];
        foreach ($references as $reference) {
            $doc=$reference->document;
            if (!$doc || $doc->document_type_id !== '01' || in_array($doc->state_type_id,['09','11'],true)) continue;
            $cashInvoices[$reference->cash_id]['ids'][$doc->id]=true;
            if ($doc->sale_note_id) $cashInvoices[$reference->cash_id]['notes'][$doc->sale_note_id]=true;
        }
        $seen=[];$rows=[];
        foreach ($references as $reference) {
            $doc=$reference->document; $note=$reference->sale_note;
            if ($type !== '03' && $doc && in_array($doc->document_type_id,['01','07','08'],true)) {
                $key='document:'.$doc->id;
                if (!isset($seen[$key])) { $rows[]=self::row($doc);$seen[$key]=true; }
            }
            if ($type !== '02' && $note) {
                $converted=$type === '01' && (
                    ($note->document_id && isset($cashInvoices[$reference->cash_id]['ids'][$note->document_id])) ||
                    isset($cashInvoices[$reference->cash_id]['notes'][$note->id])
                );
                $key='sale_note:'.$note->id;
                if (!$converted && !isset($seen[$key])) { $rows[]=self::row($note);$seen[$key]=true; }
            }
        }
        usort($rows,static fn ($a,$b) => strcmp($b['date_of_issue'],$a['date_of_issue']) ?: strcmp($a['report_key'],$b['report_key']));
        $display=[];$totals=[];
        foreach (collect($rows)->groupBy('currency_type_id') as $currency=>$group) {
            $dataset=new ReportDataset($group);
            foreach (self::FIELDS as $field) $totals[$currency][$field]=$dataset->sum($field);
            foreach (ReportProjection::money($dataset,self::FIELDS) as $row) $display[$row['report_key']]=$row['display'];
        }
        foreach ($rows as &$row) {
            foreach (self::FIELDS as $field) $row[$field]=$display[$row['report_key']][$field];
        }
        unset($row);
        return ['rows'=>$rows,'totals'=>$totals];
    }

    private static function row($record): array
    {
        $isDocument=$record instanceof Document;
        $type=$isDocument ? $record->document_type_id : '80';
        $void=in_array($record->state_type_id,['09','11'],true);$values=[];
        foreach (self::FIELDS as $field) {
            $amount=$void ? D::of(0) : D::readRequired($record,$field);
            $values[$field]=$type === '07' ? $amount->negated() : $amount;
        }
        $affected=null;
        if ($isDocument && in_array($type,['07','08'],true) && $record->note) {
            $source=$record->note->affected_document ?: $record->note->data_affected_document;
            if ($source) $affected=FiscalIdentity::numberFull($source->series ?? '',$source->number ?? '');
        }
        return array_merge(['report_key'=>($isDocument?'document:':'sale_note:').$record->id,
            'id'=>$record->id,'document_type_id'=>$type,
            'fiscal_environment'=>$record->fiscal_environment,
            'fiscal_environment_description'=>$record->fiscal_environment_type->description ?? '',
            'document_type_description'=>$isDocument ? ($record->document_type->description ?? '') : 'Nota de venta',
            'number'=>FiscalIdentity::numberFull($record->series,$record->number),
            'date_of_issue'=>$record->date_of_issue->format('Y-m-d'),'affected_document'=>$affected,
            'customer_name'=>$record->customer->name ?? '',
            'customer_number'=>$record->customer ? format_person_identity_document($record->customer) : '',
            'state_type_id'=>$record->state_type_id,'state_type_description'=>$record->state_type->description ?? '',
            'currency_type_id'=>$record->currency_type_id,'calculation_snapshot'=>D::snapshot($values)],D::values($values));
    }
}
