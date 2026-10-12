<table style="width:100%; font-size:9px; border:1px solid #777; margin-bottom:3mm;">
    @foreach($profiles as $profile)
        <tr><td>RIF fiscal {{ $profile['role'] }}: <strong>{{ $profile['rif'] }}</strong></td></tr>
        @if($profile['spe'])<tr><td>{{ $profile['role'] }}: Sujeto Pasivo Especial</td></tr>@endif
    @endforeach
</table>
