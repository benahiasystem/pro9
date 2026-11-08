@php
    $traceLots = [];
    foreach ((array) ($row->item->IdLoteSelected ?? []) as $lot) {
        $code = trim((string) data_get($lot, 'code', ''));
        $date = trim((string) data_get($lot, 'date_of_due', ''));
        if (($traceShowLots && $code !== '') || ($traceShowDates && $date !== '')) $traceLots[] = ['code' => $code, 'date' => $date];
    }
    $traceSerials = [];
    if ($traceShowSerials) {
        foreach ((array) ($row->item->lots ?? []) as $serial) {
            $series = trim((string) data_get($serial, 'series', ''));
            if (data_get($serial, 'has_sale') && $series !== '') $traceSerials[] = $series;
        }
    }
@endphp
@if(count($traceLots) || count($traceSerials))
    <tr><td colspan="{{ $traceColspan }}" class="text-left align-top" style="font-size: 9px;">
        @foreach($traceLots as $lot)
            <div>
                @if($traceShowLots && $lot['code'] !== '')
                    Lote:
                    @foreach(mb_str_split($lot['code'], 24) as $part)
                        <span>{{ $part }}</span>
                        @if(!$loop->last)<br/>@endif
                    @endforeach
                @endif
                @if($traceShowDates && $lot['date'] !== '')
                    @if($traceShowLots && $lot['code'] !== '') &nbsp; · &nbsp; @endif
                    Vencimiento: {{ $lot['date'] }}
                @endif
            </div>
        @endforeach
        @foreach($traceSerials as $code)
            <div>Serie: @foreach(mb_str_split($code, 24) as $part)<span>{{ $part }}</span>@if(!$loop->last)<br/>@endif @endforeach</div>
        @endforeach
    </td></tr>
@endif
