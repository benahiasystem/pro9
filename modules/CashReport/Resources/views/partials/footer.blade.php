{{-- Pie de página solo para A4. mPDF reemplaza {PAGENO} y {nbpg} --}}
<htmlpagefooter name="cashReportFooter">
    <table class="report-footer">
        <tr>
            <td class="left">{{ $header['printed_at'] }}</td>
            <td class="right">Página {PAGENO} de {nbpg}</td>
        </tr>
    </table>
</htmlpagefooter>
<sethtmlpagefooter name="cashReportFooter" value="on" />
