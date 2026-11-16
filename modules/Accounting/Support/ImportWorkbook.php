<?php

namespace Modules\Accounting\Support;

use Modules\Accounting\Domain\CompanyContext;
use Modules\Accounting\Models\{Account, ThirdParty, CostCenter, ImportJob, ImportUnit};
use Modules\Accounting\Services\ImportService;
use PhpOffice\PhpSpreadsheet\{Spreadsheet, Cell\DataType, Cell\Coordinate, Writer\Xlsx};

/** Only writes trusted structure and explicit text, never evaluates user formulas. */
final class ImportWorkbook
{
    public function csvTemplate(string $kind): string
    {
        return "\xEF\xBB\xBF" . implode(';', array_keys(ImportSchema::columns($kind))) . "\r\n";
    }

    public function template(string $kind): string
    {
        $columns = ImportSchema::columns($kind);
        $book = new Spreadsheet();
        $data = $book->getActiveSheet()->setTitle('Datos');
        $this->row($data, 1, array_keys($columns));
        $this->row($data, 2, [app(CompanyContext::class)->company()->tax_id]);
        foreach (array_values($columns) as $i => [$type, $required]) {
            $letter = Coordinate::stringFromColumnIndex($i + 1);
            $data->getColumnDimension($letter)->setWidth($i < 2 ? 26 : 22);
            $data->getStyle($letter . '2:' . $letter . (ImportSchema::MAX_ROWS + 1))->getNumberFormat()->setFormatCode('@');
            if ($required) {
                $data->getStyle($letter . '1')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
            }
        }
        $this->style($data, count($columns));
        $notes = $book->createSheet()->setTitle('Instrucciones');
        $instructions = [
            ['Contabilidad Benahia · ' . ImportSchema::definition($kind)['label'], 'Plantilla V' . ImportSchema::VERSION],
            ['Empresa autorizada', app(CompanyContext::class)->company()->name . ' · ' . app(CompanyContext::class)->company()->tax_id],
            ['Alcance', ImportSchema::definition($kind)['help']],
            ['Cómo completar', 'Edita únicamente Datos. Mantén los encabezados. Los ejemplos de otra hoja no se importan.'],
            ['Referencia estable', 'referencia_importacion identifica la misma unidad en archivos corregidos y reintentos. No la cambies para eludir un rechazo o repetir un registro.'],
            ['Límites', '2 MiB; 2.000 filas; 200 referencias por archivo. Un comprobante admite hasta 500 líneas. No se admite separar una unidad entre archivos.'],
            ['Números y fechas', 'Importes y tasas sin miles ni símbolos; hasta 12 decimales originales. Libro VES: máximo 2 decimales. Fechas AAAA-MM-DD. Texto para RIF, códigos, controles y referencias, conservando ceros iniciales.'],
            ['Excel', 'Pega valores como texto. No se admiten fórmulas, macros, objetos ni enlaces externos. Excel puede perder dígitos al guardar como número; el sistema no reconstruye precisión perdida.'],
            ['CSV', 'UTF-8; descarga inicial con punto y coma. Selecciona el separador y la marca decimal en la pantalla. No se permiten separadores de miles.'],
            ['Confirmación', 'Primero valida. Se importan solo las unidades válidas seleccionadas, completas y como borradores o fichas nuevas. Los rechazos se detallan por fila y campo.'],
            ['Reintentos', 'Descarga los errores para corregir unidades rechazadas. El archivo de errores conserva todas sus líneas. Las referencias ya aceptadas no crean registros adicionales.'],
            ['tipo de tercero', 'customer = cliente; supplier = proveedor; both = ambos; other = otro.'],
            ['tipo_asiento', 'regular = ordinario; adjustment = ajuste. Vacío: regular. Para clientes/proveedores usa documentos, apertura o liquidaciones; no se eluden sus auxiliares con asientos libres.'],
            ['tipo_fila de apertura', 'saldo = una fila por cuenta del balance; partida = documento pendiente por tercero. Repite fecha_corte, motivo (10–300 caracteres) y soporte_general (5–1.000) en todas las filas de la apertura.'],
            ['Partidas iniciales', 'Documento anterior al corte. Importe y tasa histórica positivos; valor_libro_ves conciliado; fecha_tasa no posterior al corte; fuente_tasa (3–255) y soporte_partida (5–1.000). Los antecedentes se pueden vincular después desde Auxiliares.'],
            ['tipo_documento', 'sale_invoice, purchase_invoice, sale_credit_note, sale_debit_note, purchase_credit_note, purchase_debit_note.'],
            ['Documentos y notas', 'base + iva + otros_impuestos = total positivo, en moneda documental VES/USD/EUR. Tasa VES = 1. La nota requiere original_numero, original_fecha y, cuando corresponda, original_control de una factura ya registrada. Completa después la revisión fiscal y sus reglas.'],
            ['Catálogos', 'La hoja Catalogos incluye hasta 2.000 referencias de cada catálogo activo de esta empresa. Verifica el catálogo completo en la aplicación si no encuentras un código.'],
        ];
        foreach ($instructions as $i => $row) {
            $this->row($notes, $i + 1, $row);
        }
        $notes->getColumnDimension('A')->setWidth(30);
        $notes->getColumnDimension('B')->setWidth(110);
        $notes->getStyle('A1:B' . count($instructions))->getAlignment()->setWrapText(true);
        $notes->getDefaultRowDimension()->setRowHeight(48);
        $this->style($notes, 2, false);
        $catalog = $book->createSheet()->setTitle('Catalogos');
        $this->row($catalog, 1, ['catalogo', 'codigo_o_rif', 'nombre', 'clasificacion']);
        $index = 2;
        foreach (Account::where('active', true)->where('posting', true)->orderBy('code')->limit(2000)->get() as $account) {
            $this->row($catalog, $index++, ['cuenta', $account->code, $account->name, $account->auxiliary ?? '']);
        }
        foreach (ThirdParty::where('active', true)->orderBy('name')->limit(2000)->get() as $party) {
            $this->row($catalog, $index++, ['tercero', $party->tax_id, $party->name, $party->kind]);
        }
        foreach (CostCenter::where('active', true)->orderBy('code')->limit(2000)->get() as $center) {
            $this->row($catalog, $index++, ['centro_costo', $center->code, $center->name, '']);
        }
        foreach (['A' => 20, 'B' => 30, 'C' => 55, 'D' => 26] as $letter => $width) {
            $catalog->getColumnDimension($letter)->setWidth($width);
        }
        $this->style($catalog, 4);
        $book->setActiveSheetIndex(0);
        return $this->bytes($book);
    }

    public function errors(ImportJob $job): string
    {
        $book = new Spreadsheet();
        $sheet = $book->getActiveSheet()->setTitle('Datos');
        $headers = array_keys(ImportSchema::columns($job->kind));
        $this->row($sheet, 1, [...$headers, ...ImportSchema::ERROR_COLUMNS]);
        $groups = app(ImportService::class)->rows($job);
        $index = 2;
        foreach (ImportUnit::where('import_job_id', $job->id)->whereNotIn('status', ['imported', 'already_imported'])->orderBy('position')->get() as $unit) {
            foreach ($groups[$unit->reference_hash] as $row) {
                $errors = array_values(array_filter($unit->errors, fn($error) => $error['row'] === $row['row'] || $error['field'] === 'unidad'));
                $messages = array_map(fn($error) => 'Fila ' . $error['row'] . ' · ' . $error['field'] . ': ' . $error['message'], $errors);
                if (!$messages) {
                    $messages = [$unit->status === 'invalid' ? 'Unidad rechazada completa; corrige también las demás filas de esta referencia.' : 'Unidad válida todavía sin confirmar.'];
                }
                $this->row($sheet, $index++, [...array_map(fn($header) => $row['data'][$header], $headers), (string) $row['row'], implode(', ', array_unique(array_column($errors, 'field'))), mb_substr(implode("\n", $messages), 0, 4096)]);
            }
        }
        for ($i = 1; $i <= count($headers) + 3; ++$i) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setWidth($i > count($headers) ? 46 : 24);
        }
        $this->style($sheet, count($headers) + 3);
        return $this->bytes($book);
    }

    private function row($sheet, int $row, array $values): void
    {
        foreach (array_values($values) as $i => $value) {
            $sheet->setCellValueExplicit(Coordinate::stringFromColumnIndex($i + 1) . $row, (string) $value, DataType::TYPE_STRING);
        }
    }

    private function style($sheet, int $columns, bool $filter = true): void
    {
        $last = Coordinate::stringFromColumnIndex($columns);
        $sheet->getStyle('A1:' . $last . '1')->getFill()->setFillType('solid')->getStartColor()->setRGB('4F46BA');
        $sheet->getStyle('A1:' . $last . '1')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getRowDimension(1)->setRowHeight(30);
        $sheet->freezePane('C2');
        if ($filter) {
            $sheet->setAutoFilter('A1:' . $last . max(1, $sheet->getHighestDataRow()));
        }
    }

    private function bytes(Spreadsheet $book): string
    {
        $file = tmpfile();
        if (!$file) {
            throw new \RuntimeException('No se puede preparar la descarga.');
        }
        try {
            $writer = new Xlsx($book);
            $writer->setPreCalculateFormulas(false);
            $writer->save(stream_get_meta_data($file)['uri']);
            rewind($file);
            return stream_get_contents($file);
        } finally {
            fclose($file);
            $book->disconnectWorksheets();
        }
    }
}
