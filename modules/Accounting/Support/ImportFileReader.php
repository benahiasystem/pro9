<?php

namespace Modules\Accounting\Support;

use Brick\Math\BigDecimal;
use Modules\Accounting\Domain\ApiProblem;

/** A bounded, non-evaluating reader. Monetary XLSX values never become floats. */
final class ImportFileReader
{
    public function read(string $path, string $kind, string $format, array $options = []): array
    {
        $columns = ImportSchema::columns($kind);
        if (!is_file($path) || filesize($path) > ImportSchema::MAX_BYTES) {
            throw new ApiProblem('IMPORT_FILE_SIZE', 'El archivo debe tener como máximo 2 MiB. Divide los lotes sin separar las líneas de un comprobante.');
        }
        $raw = match ($format) {
            'csv' => $this->csv($path, $options['delimiter'] ?? ';'),
            'xlsx' => $this->xlsx($path),
            default => throw new ApiProblem('IMPORT_FORMAT', 'Utiliza la plantilla XLSX o CSV, guardada con su extensión original.'),
        };
        if (!$raw['rows']) {
            throw new ApiProblem('IMPORT_EMPTY', 'El archivo no contiene encabezados.');
        }
        $headerRow = array_key_first($raw['rows']);
        $headers = [];
        foreach ($raw['rows'][$headerRow] as $index => $cell) {
            $name = trim($cell['value']);
            if ($name === '') {
                continue;
            }
            if (!array_key_exists($name, $columns) && !in_array($name, ImportSchema::ERROR_COLUMNS, true)) {
                throw new ApiProblem('IMPORT_HEADER', 'Encabezado desconocido: ' . mb_substr($name, 0, 100) . '. Usa los nombres de la plantilla sin modificarlos.');
            }
            if (in_array($name, $headers, true)) {
                throw new ApiProblem('IMPORT_HEADER', 'El encabezado ' . $name . ' está repetido.');
            }
            $headers[$index] = $name;
        }
        foreach ($columns as $name => [$type, $required]) {
            if ($required && !in_array($name, $headers, true)) {
                throw new ApiProblem('IMPORT_HEADER', 'Falta el encabezado obligatorio ' . $name . '.');
            }
        }
        $result = [];
        foreach ($raw['rows'] as $row => $cells) {
            if ($row === $headerRow) {
                continue;
            }
            $data = array_fill_keys(array_keys($columns), '');
            $issues = [];
            foreach ($cells as $index => $cell) {
                if (!isset($headers[$index])) {
                    if ($cell['value'] !== '') {
                        $issues[] = ImportSchema::issue($row, 'fila', 'UNNAMED_COLUMN', 'Hay un valor en una columna sin encabezado.');
                    }
                    continue;
                }
                $name = $headers[$index];
                if (in_array($name, ImportSchema::ERROR_COLUMNS, true)) {
                    continue;
                }
                $value = trim($cell['value']);
                $data[$name] = $value;
                if ($value === '') {
                    continue;
                }
                try {
                    if ($cell['type'] === 'unsupported') {
                        throw new ApiProblem('IMPORT_CELL_TYPE', 'La celda contiene un error, valor lógico o tipo no admitido. Pega el valor original como texto.');
                    }
                    $type = $columns[$name][0];
                    if ($type === 'text' && $cell['type'] !== 'text') {
                        throw new ApiProblem('IMPORT_TEXT_REQUIRED', 'Guarda este campo como texto para conservar identificadores y ceros iniciales.');
                    }
                    if ($type === 'decimal') {
                        $data[$name] = $this->decimal($value, $cell['type'] === 'number' ? '.' : ($options['decimal_mark'] ?? '.'));
                    } elseif ($type === 'date') {
                        $data[$name] = $this->date($value, $cell['type'], $raw['date1904']);
                    }
                } catch (ApiProblem $e) {
                    $issues[] = ImportSchema::issue($row, $name, $e->errorCode, $e->getMessage());
                }
            }
            // A prepared template may repeat the company RIF in otherwise empty rows.
            if (!array_filter(array_diff_key($data, ['empresa_rif' => true]), fn($v) => $v !== '') && !$issues) {
                continue;
            }
            foreach ($columns as $name => [$type, $required]) {
                if ($required && $data[$name] === '') {
                    $issues[] = ImportSchema::issue($row, $name, 'REQUIRED', 'Este campo es obligatorio.');
                }
            }
            $result[] = ['row' => $row, 'data' => $data, 'errors' => $issues];
            if (count($result) > ImportSchema::MAX_ROWS) {
                throw new ApiProblem('IMPORT_ROW_LIMIT', 'Cada archivo admite hasta 2.000 filas de datos. Divide el archivo por comprobantes completos.');
            }
        }
        if (!$result) {
            throw new ApiProblem('IMPORT_EMPTY', 'Completa la hoja Datos; las instrucciones y los ejemplos no se importan.');
        }
        return $result;
    }

    private function csv(string $path, string $delimiter): array
    {
        if (!in_array($delimiter, [';', ',', "\t"], true)) {
            throw new ApiProblem('IMPORT_DELIMITER', 'Selecciona punto y coma, coma o tabulación.');
        }
        $bytes = file_get_contents($path);
        if (!mb_check_encoding($bytes, 'UTF-8') || str_contains($bytes, "\0")) {
            throw new ApiProblem('IMPORT_ENCODING', 'Guarda el CSV con codificación UTF-8.');
        }
        $bytes = preg_replace('/^\xEF\xBB\xBF/', '', $bytes);
        $state = 'start';
        for ($i = 0, $length = strlen($bytes); $i < $length; ++$i) {
            $char = $bytes[$i];
            if ($state === 'quoted') {
                if ($char === '"') {
                    $state = 'after';
                }
            } elseif ($state === 'after' && $char === '"') {
                $state = 'quoted';
            } elseif ($char === $delimiter || $char === "\r" || $char === "\n") {
                $state = 'start';
            } elseif ($state === 'start') {
                $state = $char === '"' ? 'quoted' : 'plain';
            } elseif ($state === 'after' || $char === '"') {
                throw new ApiProblem('IMPORT_CSV', 'El CSV tiene comillas mal cerradas. Guarda de nuevo el archivo con el separador seleccionado.');
            }
        }
        if ($state === 'quoted') {
            throw new ApiProblem('IMPORT_CSV', 'El CSV contiene un campo entre comillas incompleto.');
        }
        $stream = fopen('php://temp', 'w+');
        fwrite($stream, $bytes);
        rewind($stream);
        $rows = [];
        $physicalLine = 1;
        try {
            while (!feof($stream)) {
                $start = ftell($stream);
                $row = fgetcsv($stream, 0, $delimiter, '"', '');
                $end = ftell($stream);
                if ($row === false) {
                    break;
                }
                fseek($stream, $start);
                $record = fread($stream, $end - $start);
                fseek($stream, $end);
                if (count($row) > 64) {
                    throw new ApiProblem('IMPORT_COLUMN_LIMIT', 'El archivo admite como máximo 64 columnas.');
                }
                $cells = [];
                foreach ($row as $index => $value) {
                    $value = (string) $value;
                    $this->textLimit($value);
                    $cells[$index] = ['value' => $value, 'type' => 'text'];
                }
                if (array_filter($row, fn($v) => $v !== null && trim($v) !== '')) {
                    $rows[$physicalLine] = $cells;
                }
                $physicalLine += max(1, preg_match_all('/\r\n|\r|\n/', $record));
                if (count($rows) > ImportSchema::MAX_ROWS + 1) {
                    throw new ApiProblem('IMPORT_ROW_LIMIT', 'Cada archivo admite hasta 2.000 filas de datos.');
                }
            }
        } finally {
            fclose($stream);
        }
        return ['rows' => $rows, 'date1904' => false];
    }

    public function decimal(string $value, string $mark = '.'): string
    {
        if (!in_array($mark, ['.', ','], true) || strlen($value) > 64) {
            throw new ApiProblem('IMPORT_DECIMAL', 'Utiliza un decimal sin separadores de miles.');
        }
        if ($mark === ',') {
            if (str_contains($value, '.')) {
                throw new ApiProblem('IMPORT_DECIMAL', 'Seleccionaste coma decimal. No uses puntos ni separadores de miles.');
            }
            $value = str_replace(',', '.', $value);
        }
        if (!preg_match('/^[+-]?[0-9]+(?:\.[0-9]+)?(?:[eE][+-]?[0-9]{1,2})?$/D', $value)) {
            throw new ApiProblem('IMPORT_DECIMAL', 'Utiliza un decimal sin separadores de miles ni símbolos monetarios.');
        }
        try {
            $number = (string) BigDecimal::of($value)->stripTrailingZeros();
            if (!preg_match('/^-?(?:0|[1-9][0-9]{0,19})(?:\.[0-9]{1,12})?$/D', $number)) {
                throw new \RuntimeException();
            }
            return $number;
        } catch (\Throwable) {
            throw new ApiProblem('IMPORT_PRECISION', 'El valor excede 20 dígitos enteros o 12 decimales. Conserva el valor original; no lo redondees para importarlo.');
        }
    }

    private function date(string $value, string $type, bool $date1904): string
    {
        if ($type === 'number') {
            $canonical = $this->decimal($value);
            if (!preg_match('/^[0-9]{1,7}$/D', $canonical) || (int) $canonical > 2957000 || !$date1904 && (int) $canonical === 60) {
                throw new ApiProblem('IMPORT_DATE', 'Usa una fecha válida sin hora, preferiblemente AAAA-MM-DD como texto.');
            }
            $days = (int) $canonical;
            $base = $date1904 ? '1904-01-01' : ($days < 60 ? '1899-12-31' : '1899-12-30');
            $value = (new \DateTimeImmutable($base, new \DateTimeZone('UTC')))->modify('+' . $days . ' days')->format('Y-m-d');
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value, new \DateTimeZone('UTC'));
        if (!$date || $date->format('Y-m-d') !== $value || $value < '1900-01-01' || $value > '9999-12-31') {
            throw new ApiProblem('IMPORT_DATE', 'Usa una fecha válida con formato AAAA-MM-DD.');
        }
        return $value;
    }

    private function textLimit(string $value): void
    {
        if (mb_strlen($value) > 4096 || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $value)) {
            throw new ApiProblem('IMPORT_CELL_LIMIT', 'Una celda supera 4.096 caracteres o contiene caracteres de control.');
        }
    }

    /** Parse XML with DTDs, entities, networking and deep documents disabled. */
    private function xml(string $bytes, callable $visit): void
    {
        if (strlen($bytes) > 8388608 || preg_match('/<!\s*(DOCTYPE|ENTITY)/i', $bytes)) {
            throw new ApiProblem('IMPORT_XML', 'El XLSX contiene XML no permitido o demasiado grande.');
        }
        $old = libxml_use_internal_errors(true);
        libxml_clear_errors();
        $xml = new \XMLReader();
        try {
            if (!$xml->XML($bytes, null, LIBXML_NONET | LIBXML_COMPACT)) {
                throw new ApiProblem('IMPORT_XML', 'No se puede leer el XML del XLSX.');
            }
            $xml->setParserProperty(\XMLReader::LOADDTD, false);
            $xml->setParserProperty(\XMLReader::SUBST_ENTITIES, false);
            $nodes = 0;
            while ($xml->read()) {
                if (++$nodes > 300000 || $xml->depth > 48 || in_array($xml->nodeType, [\XMLReader::DOC_TYPE, \XMLReader::ENTITY_REF], true)) {
                    throw new ApiProblem('IMPORT_XML', 'La estructura del XLSX excede los límites permitidos.');
                }
                if ($xml->nodeType === \XMLReader::ELEMENT && $xml->localName === 'f') {
                    throw new ApiProblem('IMPORT_FORMULA', 'El XLSX contiene fórmulas. Copia y pega únicamente valores antes de importarlo.');
                }
                $visit($xml);
            }
            if (libxml_get_errors()) {
                throw new ApiProblem('IMPORT_XML', 'El XML del XLSX está incompleto o no es válido.');
            }
        } finally {
            $xml->close();
            libxml_clear_errors();
            libxml_use_internal_errors($old);
        }
    }

    private function xlsx(string $path): array
    {
        $zip = new \ZipArchive();
        if ($zip->open($path, \ZipArchive::RDONLY) !== true) {
            throw new ApiProblem('IMPORT_XLSX', 'El archivo no es un XLSX válido.');
        }
        try {
            if ($zip->numFiles > 256) {
                throw new ApiProblem('IMPORT_XLSX', 'El XLSX contiene demasiadas partes.');
            }
            $parts = [];
            $size = 0;
            for ($i = 0; $i < $zip->numFiles; ++$i) {
                $stat = $zip->statIndex($i);
                $name = $stat['name'];
                $size += $stat['size'];
                if (isset($parts[$name]) || preg_match('~(?:^/|\\\\|(?:^|/)\.\.(?:/|$)|:|%|externalLinks|vbaProject|embeddings|activeX|connections|queryTables)~i', $name) || $size > 16777216 || $stat['size'] > 8388608 || ($stat['encryption_method'] ?? 0) !== 0 || !preg_match('/\.(xml|rels)$/D', $name)) {
                    throw new ApiProblem('IMPORT_XLSX', 'Usa un XLSX simple, sin macros, objetos, conexiones ni contenido externo.');
                }
                $parts[$name] = $zip->getFromIndex($i);
                if (!is_string($parts[$name]) || strlen($parts[$name]) !== $stat['size']) {
                    throw new ApiProblem('IMPORT_XLSX', 'El XLSX contiene una parte dañada.');
                }
            }
            foreach ($parts as $name => $bytes) {
                $this->xml($bytes, function ($node) use ($name) {
                    if (str_ends_with($name, '.rels') && $node->nodeType === \XMLReader::ELEMENT && $node->localName === 'Relationship') {
                        $target = $node->getAttribute('Target') ?? '';
                        if (strtolower($node->getAttribute('TargetMode') ?? '') === 'external' || preg_match('~(?:^[a-z][a-z0-9+.-]*:|^//|\\\\|[%?#])~i', $target)) {
                            throw new ApiProblem('IMPORT_EXTERNAL_CONTENT', 'El XLSX contiene enlaces o recursos externos. Elimínalos y guarda una copia con valores.');
                        }
                    }
                });
            }
            if (!isset($parts['xl/workbook.xml'], $parts['xl/_rels/workbook.xml.rels'])) {
                throw new ApiProblem('IMPORT_XLSX', 'Falta la estructura del libro XLSX.');
            }
            $sheets = [];
            $date1904 = false;
            $this->xml($parts['xl/workbook.xml'], function ($node) use (&$sheets, &$date1904) {
                if ($node->nodeType !== \XMLReader::ELEMENT) {
                    return;
                }
                if ($node->localName === 'workbookPr') {
                    $date1904 = in_array($node->getAttribute('date1904'), ['1', 'true'], true);
                } elseif ($node->localName === 'sheet') {
                    $sheets[] = ['name' => $node->getAttribute('name'), 'state' => $node->getAttribute('state') ?? 'visible', 'id' => $node->getAttributeNs('id', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships')];
                }
            });
            $data = array_values(array_filter($sheets, fn($sheet) => $sheet['name'] === 'Datos'));
            if (!$data && count($sheets) === 1) {
                $data = $sheets;
            }
            if (count($data) !== 1 || $data[0]['state'] !== 'visible') {
                throw new ApiProblem('IMPORT_SHEET', 'Debe existir una hoja visible Datos; también se admite un libro con una sola hoja visible.');
            }
            foreach ($sheets as $sheet) {
                if ($sheet['id'] !== $data[0]['id'] && !in_array($sheet['name'], ['Instrucciones', 'Ejemplos', 'Catalogos'], true)) {
                    throw new ApiProblem('IMPORT_SHEET', 'Hay varias hojas de datos. Conserva únicamente Datos y las hojas auxiliares de la plantilla.');
                }
            }
            $target = null;
            $this->xml($parts['xl/_rels/workbook.xml.rels'], function ($node) use (&$target, $data) {
                if ($node->nodeType === \XMLReader::ELEMENT && $node->localName === 'Relationship' && $node->getAttribute('Id') === $data[0]['id']) {
                    $value = $node->getAttribute('Target') ?? '';
                    $target = str_starts_with($value, '/') ? ltrim($value, '/') : 'xl/' . $value;
                }
            });
            if (!$target || !preg_match('~^xl/worksheets/[^/]+\.xml$~D', $target) || !isset($parts[$target])) {
                throw new ApiProblem('IMPORT_SHEET', 'No se encuentra la hoja de datos del XLSX.');
            }
            $strings = [];
            if (isset($parts['xl/sharedStrings.xml'])) {
                $current = null;
                $inText = false;
                $this->xml($parts['xl/sharedStrings.xml'], function ($node) use (&$strings, &$current, &$inText) {
                    if ($node->nodeType === \XMLReader::ELEMENT && $node->localName === 'si') {
                        $current = '';
                    } elseif ($node->nodeType === \XMLReader::ELEMENT && $node->localName === 't') {
                        $inText = !$node->isEmptyElement;
                    } elseif ($inText && in_array($node->nodeType, [\XMLReader::TEXT, \XMLReader::CDATA, \XMLReader::SIGNIFICANT_WHITESPACE, \XMLReader::WHITESPACE], true)) {
                        $current .= $node->value;
                        $this->textLimit($current);
                    } elseif ($node->nodeType === \XMLReader::END_ELEMENT && $node->localName === 't') {
                        $inText = false;
                    } elseif ($node->nodeType === \XMLReader::END_ELEMENT && $node->localName === 'si') {
                        $strings[] = $current ?? '';
                        $current = null;
                    }
                });
            }
            $rows = [];
            $cell = null;
            $collect = false;
            $finish = function () use (&$rows, &$cell, $strings) {
                if (!$cell) {
                    return;
                }
                $value = $cell['value'];
                $type = $cell['type'];
                if ($type === 's') {
                    if (!preg_match('/^[0-9]{1,6}$/D', $value) || !array_key_exists((int) $value, $strings)) {
                        throw new ApiProblem('IMPORT_XLSX', 'Una celda contiene una referencia de texto inválida.');
                    }
                    $value = $strings[(int) $value];
                }
                if ($value !== '') {
                    if (!preg_match('/^([A-Z]{1,3})([1-9][0-9]{0,6})$/D', $cell['reference'], $match)) {
                        throw new ApiProblem('IMPORT_XLSX', 'Una celda tiene una coordenada inválida.');
                    }
                    $column = 0;
                    foreach (str_split($match[1]) as $letter) {
                        $column = $column * 26 + ord($letter) - 64;
                    }
                    $row = (int) $match[2];
                    if ($column > 64 || $row > ImportSchema::MAX_ROWS + 1 || isset($rows[$row][$column - 1])) {
                        throw new ApiProblem('IMPORT_CELL_LIMIT', 'El XLSX supera 2.000 filas, 64 columnas o repite una celda.');
                    }
                    $this->textLimit($value);
                    $rows[$row][$column - 1] = ['value' => $value, 'type' => in_array($type, ['s', 'inlineStr', 'str', 'd'], true) ? 'text' : ($type === '' || $type === 'n' ? 'number' : 'unsupported')];
                }
                $cell = null;
            };
            $this->xml($parts[$target], function ($node) use (&$cell, &$collect, $finish) {
                if ($node->nodeType === \XMLReader::ELEMENT && $node->localName === 'c') {
                    $cell = ['reference' => $node->getAttribute('r') ?? '', 'type' => $node->getAttribute('t') ?? '', 'value' => ''];
                    if ($node->isEmptyElement) {
                        $cell = null;
                    }
                } elseif ($cell && $node->nodeType === \XMLReader::ELEMENT && in_array($node->localName, ['v', 't'], true)) {
                    $collect = !$node->isEmptyElement;
                } elseif ($cell && $collect && in_array($node->nodeType, [\XMLReader::TEXT, \XMLReader::CDATA, \XMLReader::SIGNIFICANT_WHITESPACE, \XMLReader::WHITESPACE], true)) {
                    $cell['value'] .= $node->value;
                } elseif ($node->nodeType === \XMLReader::END_ELEMENT && in_array($node->localName, ['v', 't'], true)) {
                    $collect = false;
                } elseif ($node->nodeType === \XMLReader::END_ELEMENT && $node->localName === 'c') {
                    $finish();
                }
            });
            ksort($rows);
            return ['rows' => $rows, 'date1904' => $date1904];
        } finally {
            $zip->close();
        }
    }
}
