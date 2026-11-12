<?php

namespace Modules\Accounting\Support;

/** Streaming XLSX with explicit inline strings; no formulas or numeric coercion. */
final class TextWorkbook
{
    private array $sheets = [];
    private ?\XMLWriter $writer = null;
    private int $row = 0;
    private array $widths = [];

    public function __construct(private string $directory)
    {
        if (!mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new \RuntimeException('No se puede preparar el libro de descarga.');
        }
    }

    public static function text(mixed $value): string
    {
        return preg_replace_callback('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', fn($m) => sprintf('\\u%04X', ord($m[0])), (string) ($value ?? ''));
    }

    public function sheet(string $name, array $widths = []): void
    {
        $this->end();
        if (mb_strlen($name) > 31 || preg_match('~[\\\\/\[\]:*?]~', $name) || in_array($name, array_column($this->sheets, 'name'), true)) {
            throw new \InvalidArgumentException('Nombre de hoja inválido.');
        }
        $number = count($this->sheets) + 1;
        $path = $this->directory . '/sheet' . $number . '.xml';
        $this->sheets[] = compact('name', 'path');
        $this->row = 0;
        $this->widths = $widths;
        $w = new \XMLWriter();
        if (!$w->openUri($path)) {
            throw new \RuntimeException('No se puede escribir la hoja completa.');
        }
        $this->writer = $w;
        $w->startDocument('1.0', 'UTF-8');
        $w->startElementNs(null, 'worksheet', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $w->startElement('sheetViews');
        $w->startElement('sheetView'); $w->writeAttribute('workbookViewId', '0');
        $w->startElement('pane'); $w->writeAttribute('ySplit', '1'); $w->writeAttribute('topLeftCell', 'A2'); $w->writeAttribute('activePane', 'bottomLeft'); $w->writeAttribute('state', 'frozen'); $w->endElement();
        $w->endElement(); $w->endElement();
        $w->startElement('cols');
        foreach ($widths as $index => $width) {
            $w->startElement('col'); $w->writeAttribute('min', (string) ($index + 1)); $w->writeAttribute('max', (string) ($index + 1)); $w->writeAttribute('width', (string) $width); $w->writeAttribute('customWidth', '1'); $w->endElement();
        }
        $w->endElement();
        $w->startElement('sheetData');
    }

    public function count(): int
    {
        return $this->row;
    }

    private function column(int $number): string
    {
        $out = '';
        do {
            --$number;
            $out = chr(65 + $number % 26) . $out;
            $number = intdiv($number, 26);
        } while ($number);
        return $out;
    }

    public function row(array $values, bool $heading = false, array $numericColumns = []): void
    {
        if (!$this->writer || $this->row >= 1048576 || count($values) > 16384) {
            throw new \RuntimeException('La hoja supera las dimensiones permitidas por XLSX.');
        }
        $w = $this->writer;
        ++$this->row;
        $w->startElement('row'); $w->writeAttribute('r', (string) $this->row);
        if ($heading) { $w->writeAttribute('ht', '28'); $w->writeAttribute('customHeight', '1'); }
        foreach (array_values($values) as $i => $value) {
            $text = self::text($value);
            if (mb_strlen($text) > 32767) {
                throw new \RuntimeException('Un texto supera la capacidad de una celda XLSX; conserva la versión CSV completa.');
            }
            $w->startElement('c'); $w->writeAttribute('r', $this->column($i + 1) . $this->row); $w->writeAttribute('t', 'inlineStr'); $w->writeAttribute('s', $heading ? '1' : (in_array($i, $numericColumns, true) ? '2' : '0'));
            $w->startElement('is'); $w->startElement('t'); $w->writeAttribute('xml:space', 'preserve'); $w->text($text); $w->endElement(); $w->endElement(); $w->endElement();
        }
        $w->endElement();
        if ($this->row % 100 === 0 && $w->flush() === false) {
            throw new \RuntimeException('No se pudo escribir el libro completo.');
        }
    }

    private function end(): void
    {
        if (!$this->writer) { return; }
        $this->writer->endElement();
        $this->writer->endElement();
        $this->writer->endDocument();
        if ($this->writer->flush() === false) { throw new \RuntimeException('La hoja no se escribió completamente.'); }
        $this->writer = null;
    }

    private function xml(string $root, string $namespace, callable $body): string
    {
        $w = new \XMLWriter(); $w->openMemory(); $w->startDocument('1.0', 'UTF-8'); $w->startElementNs(null, $root, $namespace); $body($w); $w->endElement(); $w->endDocument();
        return $w->outputMemory();
    }

    public function save(string $target): void
    {
        $this->end();
        $zip = new \ZipArchive();
        if ($zip->open($target, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) { throw new \RuntimeException('No se pudo preparar el XLSX.'); }
        try {
            $types = $this->xml('Types', 'http://schemas.openxmlformats.org/package/2006/content-types', function ($w) {
                foreach (['rels' => 'application/vnd.openxmlformats-package.relationships+xml', 'xml' => 'application/xml'] as $extension => $type) { $w->startElement('Default'); $w->writeAttribute('Extension', $extension); $w->writeAttribute('ContentType', $type); $w->endElement(); }
                $parts = ['/xl/workbook.xml' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml', '/xl/styles.xml' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml'];
                foreach ($this->sheets as $i => $sheet) { $parts['/xl/worksheets/sheet' . ($i + 1) . '.xml'] = 'application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml'; }
                foreach ($parts as $part => $type) { $w->startElement('Override'); $w->writeAttribute('PartName', $part); $w->writeAttribute('ContentType', $type); $w->endElement(); }
            });
            $zip->addFromString('[Content_Types].xml', $types);
            $relNs = 'http://schemas.openxmlformats.org/package/2006/relationships';
            $documentRel = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
            $zip->addFromString('_rels/.rels', $this->xml('Relationships', $relNs, function ($w) use ($documentRel) { $w->startElement('Relationship'); $w->writeAttribute('Id', 'rId1'); $w->writeAttribute('Type', $documentRel . '/officeDocument'); $w->writeAttribute('Target', 'xl/workbook.xml'); $w->endElement(); }));
            $zip->addFromString('xl/workbook.xml', $this->xml('workbook', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main', function ($w) use ($documentRel) {
                $w->writeAttribute('xmlns:r', $documentRel); $w->startElement('sheets');
                foreach ($this->sheets as $i => $sheet) { $w->startElement('sheet'); $w->writeAttribute('name', $sheet['name']); $w->writeAttribute('sheetId', (string) ($i + 1)); $w->writeAttribute('r:id', 'rId' . ($i + 1)); $w->endElement(); }
                $w->endElement();
            }));
            $zip->addFromString('xl/_rels/workbook.xml.rels', $this->xml('Relationships', $relNs, function ($w) use ($documentRel) {
                foreach ($this->sheets as $i => $sheet) { $w->startElement('Relationship'); $w->writeAttribute('Id', 'rId' . ($i + 1)); $w->writeAttribute('Type', $documentRel . '/worksheet'); $w->writeAttribute('Target', 'worksheets/sheet' . ($i + 1) . '.xml'); $w->endElement(); }
                $w->startElement('Relationship'); $w->writeAttribute('Id', 'styles'); $w->writeAttribute('Type', $documentRel . '/styles'); $w->writeAttribute('Target', 'styles.xml'); $w->endElement();
            }));
            $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Calibri"/><color rgb="FF191A38"/></font><font><b/><sz val="11"/><name val="Calibri"/><color rgb="FFFFFFFF"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF4F46BA"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="3"><xf numFmtId="49" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf><xf numFmtId="49" fontId="1" fillId="2" borderId="0" xfId="0" applyNumberFormat="1" applyFill="1" applyFont="1" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf><xf numFmtId="49" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1" applyAlignment="1"><alignment vertical="top" horizontal="right" wrapText="1"/></xf></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>');
            foreach ($this->sheets as $i => $sheet) {
                if (!$zip->addFile($sheet['path'], 'xl/worksheets/sheet' . ($i + 1) . '.xml')) { throw new \RuntimeException('Falta una hoja del libro.'); }
            }
        } finally {
            if (!$zip->close()) { throw new \RuntimeException('El XLSX no se pudo completar.'); }
        }
    }
}
