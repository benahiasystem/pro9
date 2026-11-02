<?php

namespace Modules\CashReport\Services;

use App\Models\Tenant\Cash;
use App\Services\Payments\CashLedger;
use App\Support\Exact\{Decimal as D, ReadSnapshot};

// ######## INICIO CLASIFICACION DE COBROS Y CAJA ########
final class ClassifiedCashReport
{
    public function build(Cash $cash, string $type, array $options): array
    {
        return ReadSnapshot::run($cash->getConnection(), function () use ($cash,$type,$options) {
            $data=CashLedger::report($cash); $credit=['VES'=>D::of(0),'USD'=>D::of(0)];
            foreach ($cash->cash_documents_credit as $reference) {
                $document=$reference->getDataModelAssociated();
                if ($document instanceof \App\Models\Tenant\SaleNote && $document->documents()->exists()) continue;
                if (!$document || !isset($credit[$document->currency_type_id]) || (method_exists($document,'isVoidedOrRejected') && $document->isVoidedOrRejected())) continue;
                $balance=$document instanceof \App\Models\Tenant\Document ? $document->exactBalance() : \App\Support\Exact\SaleNoteBalance::values($document)['total_difference'];
                $credit[$document->currency_type_id]=$credit[$document->currency_type_id]->plus(D::max($balance,0));
            }
            $data['credit_pending']=array_map(fn($v)=>D::represent($v),$credit);
            $data['rows']=self::rowsForReport($data['rows'],$type);
            $data['groups']=self::groupRows($data['rows']);
            $data['show_detail']=$type!=='cash_simple' && !($options['summary'] ?? false);
            return ['header'=>HeaderDataBuilder::build($cash),'classified'=>$data,'cash'=>$cash,
                'title'=>CashReportRegistry::get($type)['label']];
        });
    }

    public static function rowsForReport(array $rows, string $type): array
    {
        if (in_array($type,['general_with_payments','income_egress','payments_associated'],true)) {
            return array_values(array_filter($rows,fn($row)=>$row['method']['collection_kind']==='cash'));
        }
        if ($type==='income_summary') {
            return array_values(array_filter($rows,fn($row)=>!$row['opening'] && $row['direction']==='income'));
        }
        return $rows;
    }

    public static function groupRows(iterable $rows): array
    {
        $groups=[];
        foreach ($rows as $row) {
            if ($row['opening']) continue;
            // Names, classifications and destination labels are historical facts, not current catalog properties.
            $key=\App\Services\Payments\Classification::historicalGroupKey($row);
            $groups[$key] ??= ['method'=>$row['method'], 'currency_type_id'=>$row['currency_type_id'],
                'destination_id'=>$row['destination_id'],'destination_label'=>$row['destination_label'],'income'=>D::of(0),'egress'=>D::of(0)];
            $groups[$key][$row['direction']]=$groups[$key][$row['direction']]->plus(D::readRequired($row,'amount'));
        }
        return array_values(array_map(function($row) {
            $row['balance']=$row['income']->minus($row['egress']);
            $row['calculation_snapshot']=D::snapshot(array_intersect_key($row,array_flip(['income','egress','balance'])));
            foreach (['income','egress','balance'] as $field) $row[$field]=D::represent($row[$field]);
            return $row;
        },$groups));
    }
}
// ######## FIN CLASIFICACION DE COBROS Y CAJA ########
