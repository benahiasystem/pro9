<?php

namespace Modules\Report\Services;

use App\Support\Exact\ConsolidatedLines;
use App\Support\Exact\Decimal;
use App\Support\Exact\ReportDataset;
use App\Support\Exact\ReportProjection;
use Modules\Order\Models\OrderNoteItem;

// ######## INICIO CALCULOS EXACTOS SAB ########
/** Read order snapshots and allocate each order's header before projecting VES. */
final class OrderNoteConsolidatedAmounts
{
    public static function rows(iterable $records): array
    {
        $records = new \Illuminate\Database\Eloquent\Collection(collect($records)->values()->all());
        $reader = new ConsolidatedLines((new OrderNoteItem())->getConnection());
        $result = [];
        foreach ($records->chunk(500) as $chunk) {
            $chunk->loadMissing('order_note');
            foreach ($chunk as $row) {
                $parent = $row->order_note;
                $attributes = $parent->getAttributes();
                $row->source_type = 'order_note';
                $row->source_id = $parent->id;
                $row->document_type_id = '80';
                $row->currency_type_id = $attributes['currency_type_id'];
                $row->exchange_rate_sale = $attributes['exchange_rate_sale'];
            }
            foreach ($reader->rows($chunk) as $line) $result[$line['id']] = $line;
        }
        return $result;
    }

    public static function display(array $rows): array
    {
        $display = [];
        foreach (ReportProjection::money(new ReportDataset(array_values($rows)), ['total_sale']) as $row) {
            $display[$row['id']] = $row['display']['total_sale'];
        }
        return $display;
    }

    public static function quantity(iterable $rows)
    {
        return Decimal::sum(collect($rows)->map(fn($row)=>Decimal::readRequired($row, 'quantity')));
    }
}
// ######## FIN CALCULOS EXACTOS SAB ########
