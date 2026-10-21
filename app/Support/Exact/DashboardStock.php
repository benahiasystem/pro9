<?php

namespace App\Support\Exact;

use Illuminate\Support\Facades\DB;

// ######## INICIO CALCULOS EXACTOS PRO9 ########
final class DashboardStock
{
    public static function dataset($establishment = null): ReportDataset
    {
        $db = DB::connection('tenant');
        return ReadSnapshot::run($db,static function () use ($db,$establishment) {
            $query = $db->table('item_warehouse as iw')->join('items as i','i.id','=','iw.item_id')
                ->join('warehouses as w','w.id','=','iw.warehouse_id')
                ->where('i.is_set',false)->where('i.status',true)->where('i.unit_type_id','!=','SERV')
                ->select('iw.id','iw.item_id','iw.stock','iw.calculation_snapshot','i.description','i.stock_min',
                    'i.calculation_snapshot as item_snapshot');
            if ($establishment) $query->where('w.establishment_id',$establishment);
            $rows = new ReportDataset((function () use ($query) {
                foreach ($query->lazyById(500,'iw.id','id') as $row) yield (array)$row;
            })());
            return self::fromRows($rows);
        });
    }

    public static function fromRows(ReportDataset $rows): ReportDataset
    {
        $ordered = $rows->sortBy('item_id');
        return (new ReportDataset((function () use ($ordered) {
            $id = null; $first = null; $stock = Decimal::of(0);
            foreach ($ordered as $row) {
                if ($id !== null && $id !== $row['item_id']) {
                    if ($stock->isLessThanOrEqualTo('10')) yield self::row($first,$stock);
                    $stock = Decimal::of(0);
                }
                $id = $row['item_id']; $first = $row;
                $stock = $stock->plus(Decimal::readRequired($row,'stock'))->simplified();
            }
            if ($id !== null && $stock->isLessThanOrEqualTo('10')) yield self::row($first,$stock);
        })()))->sortExact('stock');
    }

    private static function row(array $row, $stock): array
    {
        $minimum = Decimal::readRequired(['stock_min'=>$row['stock_min'],'calculation_snapshot'=>$row['item_snapshot']],'stock_min');
        $values = ['stock'=>$stock,'stock_min'=>$minimum];
        return ['item_id'=>$row['item_id'],'product'=>$row['description'],'calculation_snapshot'=>Decimal::snapshot($values)] + Decimal::values($values);
    }
}
// ######## FIN CALCULOS EXACTOS PRO9 ########
