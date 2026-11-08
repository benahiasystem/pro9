<?php

namespace Modules\Report\Services;

use App\Support\Exact\Decimal as D;
use App\Support\Exact\JsonDecimals;
use Carbon\Carbon;

/** The room snapshot is current; stay counts use the recorded arrival and departure dates. */
final class HotelOccupancyReport
{
    public static function prepare($records, $rooms): array
    {
        $counts = ['DISPONIBLE'=>0,'OCUPADO'=>0,'LIMPIEZA'=>0,'MANTENIMIENTO'=>0,'OTRO'=>0];
        $roomRows = $rooms->map(function ($room) use (&$counts) {
            $status = $room->status;
            $counts[array_key_exists($status, $counts) ? $status : 'OTRO']++;
            return ['name'=>$room->name,'status'=>$status,'updated_at'=>optional($room->updated_at)->format('Y-m-d H:i:s')];
        });
        $stays = $records->map(function ($rent) {
            $person = $rent->person;
            $customer = $rent->customer;
            $nights = null; $arrival = null;
            try {
                $start = $rent->input_date ? Carbon::createFromFormat('!Y-m-d', $rent->input_date) : null;
                if ($start) $arrival = 1;
                $end = $rent->output_date ? Carbon::createFromFormat('!Y-m-d', $rent->output_date) : null;
                if ($start && $end && $end->greaterThanOrEqualTo($start)) $nights = $start->diffInDays($end);
            } catch (\Throwable $ignored) { /* Preserve missing or invalid historical dates as unknown. */ }
            $people = $rent->quantity_persons;
            $personNights = $nights !== null && $people !== null && $people >= 0 ? $nights * $people : null;
            $roomItem = $rent->items->where('type','HAB')->sortBy('id')->first();
            $rate = null; $currency = null;
            if ($roomItem && $roomItem->getRawOriginal('item')) {
                $source = JsonDecimals::decode($roomItem->getRawOriginal('item'));
                if (isset($source['unit_price']) || isset($source['calculation_snapshot']['values']['unit_price'])) {
                    $rate = D::readRequired($source,'unit_price');
                    $currency = in_array($source['currency_type_id'] ?? null, ['VES','USD'], true) ? $source['currency_type_id'] : null;
                }
            }
            return ['id'=>$rent->id,'name'=>$customer->name ?? $customer->description ?? optional($person)->name,
                'identity_type'=>optional(optional($person)->identity_document_type)->description,
                'identity_number'=>$person ? format_person_identity_document($person) : ($customer->number ?? null),
                'nationality'=>optional(optional($person)->nationality)->description,
                'country'=>optional(optional($person)->country)->description,
                'room'=>optional($rent->room)->name,'category'=>optional(optional($rent->room)->category)->description,
                'input_date'=>$rent->input_date,'output_date'=>$rent->output_date,'notes'=>$rent->notes,
                'people'=>$people,'arrival'=>$arrival,'nights'=>$nights,'person_nights'=>$personNights,
                'rate_exact'=>$rate ? D::fraction($rate) : null,'rate'=>$rate ? D::format($rate,2,'.','') : null,'currency'=>$currency];
        });
        return ['rooms'=>$roomRows,'counts'=>$counts,'stays'=>$stays,
            'arrivals'=>$stays->sum(fn($row)=>$row['arrival'] ?? 0), 'nights'=>$stays->sum(fn($row)=>$row['nights'] ?? 0),
            'person_nights'=>$stays->sum(fn($row)=>$row['person_nights'] ?? 0),
            'unknown_arrivals'=>$stays->filter(fn($row)=>$row['arrival'] === null)->count(),
            'unknown_nights'=>$stays->filter(fn($row)=>$row['nights'] === null)->count()];
    }
}
