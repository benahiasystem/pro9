<?php

namespace Modules\CashReport\Services;

use App\Exports\GeneralFormatExport;
use Illuminate\Support\Str;
use App\Models\Tenant\Cash;
use Mpdf\Mpdf;

/**
 * Único punto de salida de los reportes de caja: PDF (mPDF) y Excel.
 */
class CashReportRenderer
{
    const ACTION_PREVIEW = 'preview';
    const ACTION_DOWNLOAD = 'download';

    /**
     * Genera la respuesta HTTP para un tipo de reporte del catálogo.
     */
    public function render(string $type, Cash $cash, array $options = [])
    {
        [$report, $options, $payload] = $this->prepare($type, $cash, $options);

        $filename = $this->filename($type, $cash);

        if ($options['format'] === CashReportRegistry::FORMAT_EXCEL) {
            return $this->excel($report['views']['excel'], $payload, $filename);
        }

        return $this->pdf($this->pdfView($report, $options['paper']), $payload, $options['paper'], $options['action'], $filename);
    }

    /**
     * Contenido binario del PDF, para los métodos legacy que arman su propia respuesta.
     */
    public function pdfContent(string $type, Cash $cash, array $options = []): string
    {
        [$report, $options, $payload] = $this->prepare($type, $cash, $options);

        $pdf = $this->makeMpdf($options['paper']);
        $pdf->WriteHTML(view($this->pdfView($report, $options['paper']), $payload)->render());

        return $pdf->output('', 'S');
    }

    /**
     * Exportador Excel listo para ->download() o ->store(), para los métodos legacy.
     */
    public function excelExport(string $type, Cash $cash, array $options = []): GeneralFormatExport
    {
        $options['format'] = CashReportRegistry::FORMAT_EXCEL;
        [$report, $options, $payload] = $this->prepare($type, $cash, $options);

        return (new GeneralFormatExport())->view_name($report['views']['excel'])->data($payload);
    }

    public function pdf(string $view, array $data, string $paper, string $action, string $filename)
    {
        $pdf = $this->makeMpdf($paper);
        $pdf->WriteHTML(view($view, $data)->render());

        return $this->pdfResponse($pdf->output('', 'S'), $filename, $action);
    }

    public function excel(string $view, array $data, string $filename)
    {
        return (new GeneralFormatExport())->view_name($view)->data($data)->download($filename.'.xlsx');
    }

    /**
     * Caja_{tipo}_{vendedor}_{YYYYMMDD_HHmm}
     */
    public function filename(string $type, Cash $cash): string
    {
        $seller = Str::slug($cash->user->name ?? 'caja', '_');
        $date = str_replace('-', '', (string) $cash->date_opening);
        $time = substr(str_replace(':', '', (string) $cash->time_opening), 0, 4);

        return "Caja_{$type}_{$seller}_{$date}_{$time}";
    }

    protected function prepare(string $type, Cash $cash, array $options): array
    {
        $report = CashReportRegistry::get($type);

        if (! $report) {
            abort(404, 'Reporte no disponible');
        }

        $options = $this->normalizeOptions($report, $options);
        $payload = app($report['builder'])->build($cash, $options);
        $payload['options'] = $options;

        return [$report, $options, $payload];
    }

    protected function pdfView(array $report, string $paper): string
    {
        return $this->isTicket($paper)
            ? ($report['views']['ticket'] ?? $report['views']['pdf'])
            : $report['views']['pdf'];
    }

    protected function makeMpdf(string $paper): Mpdf
    {
        if ($this->isTicket($paper)) {
            $width = $paper === CashReportRegistry::PAPER_TICKET_58 ? 56 : 78;

            return new Mpdf([
                'mode' => 'utf-8',
                'format' => [$width, 430],
                'margin_top' => 3,
                'margin_right' => 3,
                'margin_bottom' => 3,
                'margin_left' => 3,
            ]);
        }

        return new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_top' => 10,
            'margin_bottom' => 18,
            'margin_left' => 10,
            'margin_right' => 10,
        ]);
    }

    protected function pdfResponse(string $content, string $filename, string $action)
    {
        $temp = tempnam(sys_get_temp_dir(), 'cash_report_');
        file_put_contents($temp, $content);

        $disposition = $action === self::ACTION_DOWNLOAD ? 'attachment' : 'inline';

        return response()->file($temp, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition.'; filename="'.$filename.'.pdf"',
        ])->deleteFileAfterSend(true);
    }

    protected function normalizeOptions(array $report, array $options): array
    {
        $options = array_merge($options, $report['defaults'] ?? []);

        $format = $options['format'] ?? CashReportRegistry::FORMAT_PDF;
        $paper = $options['paper'] ?? CashReportRegistry::PAPER_A4;
        $papers = $report['papers'] ?? [CashReportRegistry::PAPER_A4];

        if (! in_array($format, $report['formats'])) {
            abort(422, 'Formato no disponible para este reporte');
        }

        if ($format === CashReportRegistry::FORMAT_PDF && ! in_array($paper, $papers)) {
            abort(422, 'Papel no disponible para este reporte');
        }

        return array_merge($options, [
            'format' => $format,
            'paper' => $paper,
            'action' => ($options['action'] ?? null) === self::ACTION_DOWNLOAD ? self::ACTION_DOWNLOAD : self::ACTION_PREVIEW,
            'summary' => (int) ($options['summary'] ?? 0),
            'is_garage' => filter_var($options['is_garage'] ?? false, FILTER_VALIDATE_BOOLEAN),
        ]);
    }

    protected function isTicket(string $paper): bool
    {
        return in_array($paper, [CashReportRegistry::PAPER_TICKET_80, CashReportRegistry::PAPER_TICKET_58]);
    }
}
