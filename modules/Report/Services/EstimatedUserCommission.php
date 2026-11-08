<?php

namespace Modules\Report\Services;

use App\Support\Exact\ConsolidatedLines;
use App\Support\Exact\Decimal as D;
use Illuminate\Database\Eloquent\Collection;

// ######## INICIO CALCULOS EXACTOS PRO9 ########
/** Profit and commissions are estimates because the cost source is not frozen. */
final class EstimatedUserCommission
{
    public static function users($users): array
    {
        $users = new Collection(collect($users)->values()->all());
        $users->loadMissing('user_commission');
        $documents = new Collection(); $notes = new Collection();
        foreach ($users as $user) {
            foreach ($user->documents as $document) $documents->push($document);
            foreach ($user->sale_notes as $note) $notes->push($note);
        }
        $documentUtilities = self::utilitiesByUser($documents, 'document');
        $noteUtilities = self::utilitiesByUser($notes, 'sale_note');
        $rows = [];
        foreach ($users as $user) {
            $utility = ($documentUtilities[$user->id] ?? D::of(0))->plus($noteUtilities[$user->id] ?? D::of(0));
            $utilities = ['calculation_snapshot'=>D::snapshot(['total_utility'=>$utility])];
            $commission = self::commission($user, $utilities);
            $rows[$user->id] = ['report_key'=>'user:'.$user->id,
                'total_utility'=>D::represent($utility), 'commission'=>D::represent($commission),
                'calculation_snapshot'=>D::snapshot(['total_utility'=>$utility, 'commission'=>$commission])];
        }
        return $rows;
    }

    public static function utility($records, string $source)
    {
        return D::sum(self::utilitiesByUser($records, $source));
    }

    private static function utilitiesByUser($records, string $source): array
    {
        $lines = new Collection(); $owners = [];
        foreach ($records as $record) {
            foreach ($record->items as $line) {
                $line->setRelation($source, $record);
                $line->source_type = $source;
                $line->source_id = $record->id;
                $line->currency_type_id = $record->getAttributes()['currency_type_id'];
                $line->exchange_rate_sale = $record->getAttributes()['exchange_rate_sale'];
                $line->document_type_id = $source === 'document' ? $record->document_type_id : '80';
                $lines->push($line);
                $owners[spl_object_id($line)] = $record->user_id;
            }
        }
        if ($lines->isEmpty()) return [];
        if ($source === 'document') $lines->loadMissing('document.note');
        $goods = $lines->filter(fn($line)=>($line->item->unit_type_id ?? null) !== 'SERV');
        $costs = EstimatedPurchaseCost::forLines($goods, $source, true);
        $reader = new ConsolidatedLines($lines->first()->getConnection());
        $sums = [];
        foreach ($lines->chunk(500) as $chunk) {
            $chunk = $chunk->values();
            foreach ($reader->rows($chunk) as $position=>$value) {
                $line = $chunk[$position];
                $cost = ($line->item->unit_type_id ?? null) === 'SERV' ? D::of(0) : $costs[spl_object_id($line)]['amount'];
                if ($line->document_type_id === '08' || ($line->document_type_id === '07'
                    && !in_array($line->document->note->note_credit_type_id ?? null, ['01','07'], true))) $cost = D::of(0);
                if ($line->document_type_id === '07') $cost = $cost->negated();
                $owner = $owners[spl_object_id($line)];
                $sums[$owner] = ($sums[$owner] ?? D::of(0))->plus(D::readRequired($value, 'total_sale'))->minus($cost)->simplified();
            }
        }
        return $sums;
    }

    public static function commission($user, $utilities)
    {
        $amount = D::readRequired($user->user_commission, 'amount');
        $utility = D::readRequired($utilities, 'total_utility');
        return $utility->multipliedBy($user->user_commission->type === 'amount' ? $amount : $amount->dividedBy(100))->simplified();
    }
}
// ######## FIN CALCULOS EXACTOS PRO9 ########
