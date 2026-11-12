<?php

namespace Modules\Accounting\Services;

use Brick\Math\BigDecimal;
use Modules\Accounting\Domain\ApiProblem;
use Modules\Accounting\Models\ReportJob;
use Modules\Accounting\Support\{ReportSchema, TextWorkbook};

final class ReportRenderer
{
    public function revision(): string
    {
        return hash('sha256', file_get_contents(__FILE__) . file_get_contents(dirname(__DIR__) . '/Support/TextWorkbook.php') . file_get_contents(dirname(__DIR__) . '/Support/ReportSchema.php'));
    }

    public function write(ReportJob $job, string $format, string $target): void
    {
        match ($format) {
            'csv' => $this->csv($job, $target),
            'xlsx' => $this->xlsx($job, $target),
            'pdf' => $this->pdf($job, $target),
            default => throw new ApiProblem('REPORT_FORMAT', 'Selecciona PDF, XLSX o CSV.'),
        };
    }

    private function display(array $column, mixed $value): string
    {
        if ($value === null || $value === '') { return ''; }
        $text = TextWorkbook::text($value);
        if ($column['type'] === 'money') { return (string) BigDecimal::of($text)->toScale(2); }
        $labels = ['asset' => 'Activo', 'liability' => 'Pasivo', 'equity' => 'Patrimonio', 'income' => 'Ingresos', 'cost' => 'Costos', 'expense' => 'Gastos', 'receivable' => 'Por cobrar', 'payable' => 'Por pagar', 'open' => 'Abierta', 'settled' => 'Liquidada', 'resolved' => 'Resuelta', 'draft' => 'Borrador', 'received' => 'Recibido', 'validated' => 'Validado', 'source_changed' => 'Origen modificado', 'changed' => 'Origen modificado', 'proposed' => 'Propuesto', 'posted' => 'Contabilizado', 'excluded' => 'Excluido', 'opening_antecedent' => 'Antecedente de apertura', 'sale_invoice' => 'Factura de venta', 'purchase_invoice' => 'Factura de compra', 'sale_credit_note' => 'Nota de crédito de venta', 'sale_debit_note' => 'Nota de débito de venta', 'purchase_credit_note' => 'Nota de crédito de compra', 'purchase_debit_note' => 'Nota de débito de compra', 'manual' => 'Manual', 'pro9' => 'PRO9'];
        return in_array($column['key'], ['kind', 'report_group', 'side', 'status', 'status_at_cutoff', 'origin'], true) ? ($labels[$text] ?? $text) : $text;
    }

    private function metadata(ReportJob $job, array $header): array
    {
        return ['Informe' => $header['title'], 'Empresa' => $header['company']['name'], 'RIF' => $header['company']['tax_id'], 'Domicilio' => $header['company']['address'] ?? '', 'Desde' => $header['from'], 'Hasta / corte' => $header['to'], 'Generado por el servidor' => $header['generated_at'], 'Usuario que conservó la versión' => $header['created_by']['name'], 'Versión de datos' => (string) $header['data_version'], 'Versión del formato' => $header['format_version'], 'Versión de presentación' => $header['presentation_version'], 'Huella de clasificación' => $header['presentation_hash'], 'ID de versión conservada' => $job->id, 'Huella SHA-256 de datos' => $job->sha256, 'Filas completas' => (string) $job->row_count, 'Alcance' => $header['scope_note'], ...(!empty($header['preliminary']) ? ['Presentación' => 'Estado financiero preliminar; no sustituye la revisión y autorización del contador.'] : []), 'Precisión' => 'Importes y tasas conservados como texto decimal. No se ejecutan fórmulas. Los caracteres de control no imprimibles se muestran como códigos Unicode.'];
    }

    private function controls(array $header): array
    {
        $out = [];
        if (isset($header['opening'])) { $out['Saldo inicial VES'] = $header['opening']; }
        foreach ($header['totals'] as $key => $value) { $out[ReportSchema::labels()[$key] ?? $key] = (string) $value; }
        foreach ($header['comparison']['totals'] ?? [] as $key => $value) { $out['Comparativo · ' . (ReportSchema::labels()[$key] ?? $key)] = (string) $value; }
        foreach ($header['comparison']['difference_totals'] ?? [] as $key => $value) { $out['Variación · ' . (ReportSchema::labels()[$key] ?? $key)] = (string) $value; }
        return $out;
    }

    private function csvSafe(string $value, bool $decimal = false): string
    {
        if ($decimal && preg_match('/^-?[0-9]+(?:\.[0-9]+)?$/D', $value)) { return $value; }
        return preg_match('/^(?:[\s\p{Z}]*[=+@\-\x{FF1D}\x{FF0B}\x{FF0D}\x{FF20}]|[\t\r\n])/u', $value) ? "'" . $value : $value;
    }

    private function csv(ReportJob $job, string $target): void
    {
        $file = fopen($target, 'xb');
        if (!$file) { throw new \RuntimeException('No se puede preparar el CSV.'); }
        $header = null;
        $columns = [];
        $prefix = [];
        try {
            fwrite($file, "\xEF\xBB\xBF");
            foreach (app(ReportExportService::class)->read($job) as $record) {
                if ($record['type'] === 'header') {
                    $header = $record['report']; $columns = $header['columns'];
                    $prefix = array_map(fn($v) => $this->csvSafe((string) $v), [$header['company']['name'], $header['company']['tax_id'], $header['generated_at'], $header['format_version'], $header['data_version'], $header['title'], $job->id, $job->sha256, json_encode($header['filter_labels'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)]);
                    fputcsv($file, ['Tipo de registro', 'Empresa', 'RIF', 'Generado', 'Versión formato', 'Versión datos', 'Informe', 'ID versión conservada', 'Huella datos', 'Filtros', ...array_column($columns, 'label'), 'Control', 'Valor de control / nota'], ';', '"', '');
                    foreach ($this->metadata($job, $header) as $key => $value) { fputcsv($file, ['identificacion', ...$prefix, ...array_fill(0, count($columns), ''), $key, $this->csvSafe($value)], ';', '"', ''); }
                    foreach ($header['opening_groups'] ?? [] as $group) {
                        $data = [...$group, 'balance' => $group['opening']];
                        fputcsv($file, ['saldo_inicial', ...$prefix, ...array_map(fn($c) => $this->csvSafe($this->display($c, $data[$c['key']] ?? ''), in_array($c['type'], ['money', 'decimal'], true)), $columns), 'Saldo inicial del grupo VES', $group['opening']], ';', '"', '');
                    }
                } elseif ($record['type'] === 'row') {
                    fputcsv($file, ['detalle', ...$prefix, ...array_map(fn($c) => $this->csvSafe($this->display($c, $record['row'][$c['key']] ?? ''), in_array($c['type'], ['money', 'decimal'], true)), $columns), '', ''], ';', '"', '');
                } elseif ($record['type'] === 'footer') {
                    foreach ($this->controls($header) as $key => $value) { fputcsv($file, ['control', ...$prefix, ...array_fill(0, count($columns), ''), $key, $this->csvSafe($value, true)], ';', '"', ''); }
                    foreach ($header['currency_totals'] ?? [] as $group) {
                        fputcsv($file, ['control_moneda', ...$prefix, ...array_fill(0, count($columns), ''), $this->csvSafe(json_encode(array_diff_key($group, ['amount' => true]), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)), $this->csvSafe((string) $group['amount'], true)], ';', '"', '');
                    }
                    fputcsv($file, ['nota', ...$prefix, ...array_fill(0, count($columns), ''), 'Lectura CSV', 'Filtra Tipo de registro = detalle para los movimientos. Los controles y saldos iniciales se identifican por separado. Importa RIF, referencias e importes como texto para que la hoja de cálculo conserve todos los dígitos; los textos con prefijo de fórmula se protegen con apóstrofo.'], ';', '"', '');
                }
            }
        } finally { fclose($file); }
    }

    private function cleanup(string $directory): void
    {
        if (!is_dir($directory) || is_link($directory)) { return; }
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $entry) {
            if ($entry->isLink() || $entry->isFile()) { unlink($entry->getPathname()); }
            elseif ($entry->isDir()) { rmdir($entry->getPathname()); }
        }
        rmdir($directory);
    }

    private function xlsx(ReportJob $job, string $target): void
    {
        $directory = $target . '-xlsx'; $book = new TextWorkbook($directory); $header = null; $columns = []; $part = 0;
        try {
            foreach (app(ReportExportService::class)->read($job) as $record) {
                if ($record['type'] === 'header') {
                    $header = $record['report']; $columns = $header['columns'];
                    $book->sheet('Identificación', [38, 110]); $book->row(['Dato', 'Valor conservado'], true);
                    foreach ($this->metadata($job, $header) as $key => $value) { $book->row([$key, $value]); }
                    $book->row(['Filtros', '']); foreach ($header['filter_labels'] as $key => $value) { $book->row([$key, $value]); }
                    $book->sheet('Detalle ' . ++$part, array_map(fn($c) => $c['type'] === 'money' || $c['type'] === 'decimal' ? 26 : (in_array($c['key'], ['concept', 'account_name', 'party_name', 'resolution'], true) ? 48 : 25), $columns));
                    $book->row(array_column($columns, 'label'), true);
                } elseif ($record['type'] === 'row') {
                    if ($book->count() === 1048576) { $book->sheet('Detalle ' . ++$part); $book->row(array_column($columns, 'label'), true); }
                    $book->row(array_map(fn($c) => $this->display($c, $record['row'][$c['key']] ?? ''), $columns), false, array_keys(array_filter($columns, fn($c) => in_array($c['type'], ['money', 'decimal'], true))));
                } elseif ($record['type'] === 'footer') {
                    $book->sheet('Controles', [52, 34]); $book->row(['Control del conjunto completo', 'Valor'], true);
                    $book->row(['Filas de detalle', (string) $record['rows']]); foreach ($this->controls($header) as $key => $value) { $book->row([$key, $value]); }
                    if (!empty($header['opening_groups'])) {
                        $book->sheet('Saldos iniciales', [25, 45, 30, 48, 28]); $book->row(['Cuenta', 'Descripción', 'RIF tercero', 'Tercero', 'Saldo inicial VES'], true);
                        foreach ($header['opening_groups'] as $group) { $book->row([$group['account_code'], $group['account_name'], $group['party_tax_id'] ?? '', $group['party_name'] ?? '', $group['opening']]); }
                    }
                    if (!empty($header['currency_totals'])) {
                        $book->sheet('Controles por moneda', [26, 34, 30, 40]); $book->row(['Moneda', 'Tipo / auxiliar', 'Estado', 'Importe original'], true);
                        foreach ($header['currency_totals'] as $group) { $book->row([$group['currency'], $group['kind'] ?? $group['side'] ?? '', $group['status'] ?? '', $group['amount']]); }
                    }
                    $book->sheet('Versiones de períodos', [20, 20, 20, 25, 48]); $book->row(['Ejercicio', 'Mes', 'Versión', 'Estado', 'Huella del cierre'], true);
                    foreach ($header['period_versions'] as $period) { $book->row([(string) $period['year'], (string) $period['month'], (string) $period['version'], $period['status'], $period['close_snapshot']['sha256'] ?? '']); }
                    $book->sheet('Presentación', [45, 24, 46, 26, 25, 30, 18]); $book->row(['ID cuenta', 'Código', 'Nombre', 'Clasificación', 'Naturaleza', 'Grupo', 'Versión'], true);
                    foreach ($header['presentation'] as $account) { $book->row([$account['id'], $account['code'], $account['name'], $account['kind'], $account['nature'], $account['report_group'] ?? '', (string) $account['version']]); }
                }
            }
            $book->save($target);
        } finally { unset($book); $this->cleanup($directory); }
    }

    private function html(mixed $value): string
    {
        return htmlspecialchars(TextWorkbook::text($value), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function pdfNumber(string $value, string $type): string
    {
        if ($value === '') { return ''; }
        $exact = $type === 'money' ? (string) BigDecimal::of($value)->toScale(2) : (string) BigDecimal::of($value)->stripTrailingZeros();
        [$whole, $fraction] = array_pad(explode('.', $exact, 2), 2, '');
        $whole = preg_replace('/\B(?=(\d{3})+(?!\d))/', '.', $whole);
        return $whole . ($fraction !== '' ? ',' . $fraction : '');
    }

    private function pdf(ReportJob $job, string $target): void
    {
        $cache = $target . '-pdf-cache'; if (!mkdir($cache, 0700, true) && !is_dir($cache)) { throw new \RuntimeException('No se puede preparar la representación PDF.'); }
        $pdf = null; $header = null; $columns = []; $primary = []; $main = ''; $buffer = ''; $count = 0;
        try {
            $pdf = new \Mpdf\Mpdf(['tempDir' => $cache, 'format' => 'A4-L', 'margin_left' => 12, 'margin_right' => 12, 'margin_top' => 27, 'margin_bottom' => 18, 'margin_header' => 6, 'margin_footer' => 7, 'default_font' => 'dejavusans', 'default_font_size' => 8, 'setAutoTopMargin' => 'stretch', 'setAutoBottomMargin' => 'stretch']);
            $pdf->shrink_tables_to_fit = 1;
            $pdf->SetAuthor('Benahia System');
            $pdf->SetTitle(ReportSchema::title($job->kind));
            $pdf->WriteHTML('body{font-family:dejavusans;color:#191A38;font-size:8pt}h1{font-size:18pt;color:#191A38;margin:4mm 0}h2{font-size:11pt;color:#4F46BA;margin:5mm 0 2mm}.meta{color:#62617B;font-size:7pt;line-height:1.6}.scope{border-left:1mm solid #4F46BA;background:#F7F7FC;padding:3mm;margin:3mm 0}.preliminary{color:#8D6116;background:#FFF5DD;padding:2mm}table{width:100%;border-collapse:collapse;margin:2mm 0}thead{display:table-header-group}th{background:#EBE8FF;color:#30296B;font-size:7pt;text-align:left;padding:2.2mm;border-bottom:.2mm solid #CEC8EB}td{font-size:8pt;vertical-align:top;padding:2.2mm;border-bottom:.15mm solid #E5E3EF}.amount{text-align:right;white-space:nowrap;font-variant-numeric:tabular-nums}.long-amount{font-size:6pt}.secondary{font-size:6.3pt;color:#64617D;line-height:1.5;margin-top:1mm}.controls td{padding:1.5mm}.record{border-bottom:.2mm solid #DDD9EE;padding:3mm 0}.record p{line-height:1.6}.nowrap{white-space:nowrap}', \Mpdf\HTMLParserMode::HEADER_CSS);
            foreach (app(ReportExportService::class)->read($job) as $record) {
                if ($record['type'] === 'header') {
                    $header = $record['report']; $columns = $header['columns'];
                    $pdf->SetHTMLHeader('<div style="font-size:7pt;color:#62617B"><b>' . $this->html($header['company']['name']) . '</b> · RIF ' . $this->html($header['company']['tax_id']) . '<br>' . $this->html($header['title']) . ' · Datos ' . $header['data_version'] . ' · Formato ' . $this->html($header['format_version']) . '</div>');
                    $pdf->SetHTMLFooter('<div style="font-size:6.5pt;color:#62617B">Benahia System · Versión ' . $this->html(substr($job->id, 0, 8)) . ' · Página {PAGENO} de {nbpg}</div>');
                    $pdf->WriteHTML('<h1>' . $this->html($header['title']) . '</h1><div class="meta"><b>' . $this->html($header['company']['name']) . '</b> · RIF ' . $this->html($header['company']['tax_id']) . '<br>' . $this->html($header['company']['address'] ?? '') . '<br>Generado: ' . $this->html($header['generated_at']) . ' · Conservado por: ' . $this->html($header['created_by']['name']) . '<br>Presentación: ' . $this->html($header['presentation_version']) . '</div><div class="scope">' . $this->html($header['scope_note']) . '</div>' . (!empty($header['preliminary']) ? '<p class="preliminary">Estado financiero preliminar. Requiere revisión y autorización del contador.</p>' : ''));
                    $filterText = []; foreach ($header['filter_labels'] as $key => $value) { $filterText[] = $this->html($key) . ': ' . $this->html($value); }
                    $pdf->WriteHTML('<div class="meta">' . implode('<br>', $filterText) . '</div>');
                    $keys = match ($job->kind) {
                        'journal' => ['accounting_date', 'reference', 'account_code', 'concept', 'debit', 'credit'],
                        'ledger' => ['accounting_date', 'reference', 'concept', 'debit', 'credit', 'balance'],
                        'auxiliary' => ['accounting_date', 'account_code', 'party_name', 'reference', 'debit', 'credit', 'balance'],
                        'trial-balance' => ['code', 'name', 'opening', 'debit', 'credit', 'balance'],
                        'open-items' => ['account_code', 'party_name', 'reference', 'currency', 'remaining_original', 'remaining_book'],
                        'documents' => ['issue_date', 'number', 'party_name', 'status', 'currency', 'total'],
                        default => array_column($columns, 'key'),
                    };
                    $primary = array_values(array_filter($columns, fn($c) => in_array($c['key'], $keys, true)));
                    $main = match ($job->kind) { 'journal', 'ledger' => 'concept', 'auxiliary', 'open-items', 'documents' => 'party_name', 'trial-balance', 'balance-sheet', 'income-statement' => 'name', default => 'title' };
                } elseif ($record['type'] === 'row') {
                    $row = $record['row'];
                    if ($job->kind === 'incidents') {
                        $block = '<div class="record">'; foreach ($columns as $column) { $block .= '<p><b>' . $this->html($column['label']) . ':</b> ' . nl2br($this->html($this->display($column, $row[$column['key']] ?? ''))) . '</p>'; } $pdf->WriteHTML($block . '</div>');
                    } else {
                        if (!$count) { $buffer = $this->pdfTableHeader($primary); }
                        $buffer .= '<tr>';
                        foreach ($primary as $column) {
                            $value = $this->display($column, $row[$column['key']] ?? ''); $numeric = in_array($column['type'], ['money', 'decimal'], true);
                            if ($numeric) { $value = $this->pdfNumber($value, $column['type']); }
                            $buffer .= '<td class="' . ($numeric ? 'amount' . (strlen($value) > 18 ? ' long-amount' : '') : '') . '">' . nl2br($this->html($value));
                            if ($column['key'] === $main) {
                                $details = [];
                                foreach ($columns as $extra) {
                                    if (!in_array($extra['key'], array_column($primary, 'key'), true) && isset($row[$extra['key']]) && $row[$extra['key']] !== '') {
                                        $extraValue = $this->display($extra, $row[$extra['key']]);
                                        if (in_array($extra['type'], ['money', 'decimal'], true)) { $extraValue = $this->pdfNumber($extraValue, $extra['type']); }
                                        $details[] = $this->html($extra['label']) . ': ' . nl2br($this->html($extraValue));
                                    }
                                }
                                if ($details) { $buffer .= '<div class="secondary">' . implode(' · ', $details) . '</div>'; }
                            }
                            $buffer .= '</td>';
                        }
                        $buffer .= '</tr>';
                        if (++$count === 100) { $pdf->WriteHTML($buffer . '</tbody></table>'); $buffer = ''; $count = 0; }
                    }
                } elseif ($record['type'] === 'footer') {
                    if ($buffer) { $pdf->WriteHTML($buffer . '</tbody></table>'); $buffer = ''; }
                    if (!$record['rows']) { $pdf->WriteHTML('<p>Sin registros para los filtros de esta versión.</p>'); }
                    $html = '<h2>Controles del conjunto completo · ' . $record['rows'] . ' filas</h2><table class="controls"><tbody>'; foreach ($this->controls($header) as $key => $value) { $html .= '<tr><td>' . $this->html($key) . '</td><td class="amount">' . $this->html(str_contains($key, 'VES') ? $this->pdfNumber($value, 'money') : $value) . '</td></tr>'; }
                    $pdf->WriteHTML($html . '</tbody></table>');
                    if (!empty($header['opening_groups'])) {
                        $html = '<h2>Saldos iniciales por cuenta y tercero</h2><table><thead><tr><th>Cuenta</th><th>Tercero / RIF</th><th>Saldo inicial VES</th></tr></thead><tbody>';
                        foreach ($header['opening_groups'] as $group) { $html .= '<tr><td>' . $this->html($group['account_code'] . ' · ' . $group['account_name']) . '</td><td>' . $this->html(($group['party_name'] ?? '') . ' · ' . ($group['party_tax_id'] ?? '')) . '</td><td class="amount">' . $this->html($this->pdfNumber($group['opening'], 'money')) . '</td></tr>'; }
                        $pdf->WriteHTML($html . '</tbody></table>');
                    }
                    if (!empty($header['currency_totals'])) {
                        $html = '<h2>Controles por moneda y tipo</h2><table><thead><tr><th>Moneda</th><th>Tipo / auxiliar</th><th>Estado</th><th>Importe original</th></tr></thead><tbody>';
                        foreach ($header['currency_totals'] as $group) { $html .= '<tr><td>' . $this->html($group['currency']) . '</td><td>' . $this->html($this->display(['key' => 'kind', 'type' => 'text'], $group['kind'] ?? $group['side'] ?? '')) . '</td><td>' . $this->html($this->display(['key' => 'status', 'type' => 'text'], $group['status'] ?? '')) . '</td><td class="amount">' . $this->html($this->pdfNumber($group['amount'], 'decimal')) . '</td></tr>'; }
                        $pdf->WriteHTML($html . '</tbody></table>');
                    }
                    $pdf->WriteHTML('<div class="meta">ID de versión: ' . $this->html($job->id) . '<br>Huella SHA-256 de datos: ' . $this->html($job->sha256) . '<br>Huella de clasificación: ' . $this->html($header['presentation_hash']) . '<br>' . $this->html($header['opening_note'] ?? '') . '<br>' . $this->html($header['basis_note'] ?? '') . '</div>');
                }
            }
            $pdf->Output($target, \Mpdf\Output\Destination::FILE);
        } finally { unset($pdf); $this->cleanup($cache); }
    }

    private function pdfTableHeader(array $columns): string
    {
        $html = '<table><thead><tr>';
        foreach ($columns as $column) { $html .= '<th' . (in_array($column['type'], ['money', 'decimal'], true) ? ' style="text-align:right"' : '') . '>' . $this->html($column['label']) . '</th>'; }
        return $html . '</tr></thead><tbody>';
    }
}
