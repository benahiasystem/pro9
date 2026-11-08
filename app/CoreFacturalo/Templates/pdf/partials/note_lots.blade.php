{{-- ######## INICIO LOTES CONGELADOS EN NOTAS PDF ######## --}}
@foreach(\App\Support\Exact\PdfNoteLots::codes($row) as $lotCode)
    <br/><span style="font-size: 8px">Lote:
        @foreach(mb_str_split($lotCode, 20) as $part)
            @if(!$loop->first)<br/>@endif{{ $part }}
        @endforeach
    </span>
@endforeach
{{-- ######## FIN LOTES CONGELADOS EN NOTAS PDF ######## --}}
