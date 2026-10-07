{{-- ######## INICIO TASAS OCHO DECIMALES ######## --}}
<script>
(function () {
{!! str_replace(['export class ', 'export const ', 'export function '], ['class ', 'const ', 'function '], file_get_contents(resource_path('js/helpers/exchange-rate-math.js'))) !!}
window.Pro9ExchangeRateMath = {exactAmount, normalizeExchangeRate, rateMultiply, rateDivide};
})();
</script>
{{-- ######## FIN TASAS OCHO DECIMALES ######## --}}
