<?php

// ########### INICIO CAMBIO VALIDACIÓN PREVIA IMPORTACIÓN ITEMS
namespace App\Support\ItemImport;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Throwable;

class ItemImportWorkbookValidator
{
    /** @var ItemImportContract */
    private $contract;

    /** @var ItemImportCatalogs */
    private $catalogs;

    public function __construct(ItemImportContract $contract, ItemImportCatalogs $catalogs)
    {
        $this->contract = $contract;
        $this->catalogs = $catalogs;
    }

    public function validate(string $path): ItemImportValidationResult
    {
        try {
            $reader = IOFactory::createReader('Xlsx');
            $reader->setReadDataOnly(false);
            $spreadsheet = $reader->load($path);
        } catch (Throwable $exception) {
            return new ItemImportValidationResult([
                $this->error(1, 1, 'El archivo XLSX está dañado o no puede leerse.'),
            ], 0);
        }

        $errors = [];

        if ($spreadsheet->getSheetCount() !== 1) {
            $errors[] = $this->error(1, 1, 'El archivo debe contener exactamente una hoja.');
        }

        $sheet = $spreadsheet->getSheet(0);
        $highestColumn = Coordinate::columnIndexFromString($sheet->getHighestDataColumn());
        $highestRow = max(1, $sheet->getHighestDataRow());
        $headerCount = max(ItemImportContract::OPTIONAL_IMAGE_COLUMN_INDEX + 1, $highestColumn);
        $headers = [];

        for ($column = 1; $column <= $headerCount; $column++) {
            $headers[] = $sheet->getCellByColumnAndRow($column, 1)->getValue();
        }

        $errors = array_merge($errors, $this->contract->validateHeaders($headers));

        if ($highestColumn > ItemImportContract::OPTIONAL_IMAGE_COLUMN_INDEX + 1) {
            for (
                $column = ItemImportContract::OPTIONAL_IMAGE_COLUMN_INDEX + 2;
                $column <= $highestColumn;
                $column++
            ) {
                $value = $sheet->getCellByColumnAndRow($column, 1)->getValue();

                if (!$this->contract->isEmpty($value)) {
                    $errors[] = $this->error(
                        1,
                        $column,
                        'La columna no forma parte del contrato de importación de productos.'
                    );
                }
            }
        }

        $catalogs = $this->catalogs->snapshot();
        $existingInternalIds = array_map('strval', $catalogs['existing_internal_ids'] ?? []);
        $internalIdRows = [];
        $totalRows = 0;
        $hasImageData = false;

        for ($rowNumber = 2; $rowNumber <= $highestRow; $rowNumber++) {
            $row = [];
            $hasExtraData = false;

            for ($column = 1; $column <= ItemImportContract::OPTIONAL_IMAGE_COLUMN_INDEX + 1; $column++) {
                $cell = $sheet->getCellByColumnAndRow($column, $rowNumber);
                $row[] = $cell->getValue();

                if ($cell->getDataType() === DataType::TYPE_FORMULA) {
                    $errors[] = $this->error(
                        $rowNumber,
                        $column,
                        'No se permiten fórmulas en los datos de importación.'
                    );
                }
            }

            if ($highestColumn > ItemImportContract::OPTIONAL_IMAGE_COLUMN_INDEX + 1) {
                for (
                    $column = ItemImportContract::OPTIONAL_IMAGE_COLUMN_INDEX + 2;
                    $column <= $highestColumn;
                    $column++
                ) {
                    $value = $sheet->getCellByColumnAndRow($column, $rowNumber)->getValue();

                    if (!$this->contract->isEmpty($value)) {
                        $hasExtraData = true;
                        $errors[] = $this->error(
                            $rowNumber,
                            $column,
                            'La columna no forma parte del contrato de importación de productos.'
                        );
                    }
                }
            }

            if ($this->rowIsEmpty($row) && !$hasExtraData) {
                continue;
            }

            $totalRows++;
            $hasImageData = $hasImageData || !$this->contract->isEmpty($row[ItemImportContract::OPTIONAL_IMAGE_COLUMN_INDEX] ?? null);
            $internalId = $this->contract->textValue($row[1] ?? null);
            $updatesExistingItem = $internalId !== '' && in_array($internalId, $existingInternalIds, true);
            $errors = array_merge(
                $errors,
                $this->contract->validateRow($row, $rowNumber, $catalogs, $updatesExistingItem)
            );

            if ($internalId !== '') {
                $internalIdRows[$internalId][] = $rowNumber;
            }
        }

        if ($hasImageData && $this->contract->isEmpty($headers[ItemImportContract::OPTIONAL_IMAGE_COLUMN_INDEX] ?? null)) {
            $errors[] = $this->error(1, ItemImportContract::OPTIONAL_IMAGE_COLUMN_INDEX + 1,
                'La columna de imágenes debe tener el encabezado "URL Imagen".');
        }

        foreach ($internalIdRows as $internalId => $rows) {
            if (count($rows) < 2) {
                continue;
            }

            foreach ($rows as $rowNumber) {
                $errors[] = $this->error(
                    $rowNumber,
                    2,
                    sprintf('El código interno "%s" está repetido dentro del archivo.', $internalId)
                );
            }
        }

        if ($totalRows === 0) {
            $errors[] = $this->error(2, 1, 'El archivo no contiene productos para importar.');
        }

        $spreadsheet->disconnectWorksheets();

        return new ItemImportValidationResult($this->groupErrors($errors), $totalRows);
    }

    private function rowIsEmpty(array $row): bool
    {
        foreach ($row as $value) {
            if (!$this->contract->isEmpty($value)) {
                return false;
            }
        }

        return true;
    }

    private function groupErrors(array $errors): array
    {
        $grouped = [];

        foreach ($errors as $error) {
            $key = $error['row'] . ':' . $error['column'];

            if (!isset($grouped[$key])) {
                $grouped[$key] = $error;
                continue;
            }

            $grouped[$key]['messages'] = array_values(array_unique(array_merge(
                $grouped[$key]['messages'],
                $error['messages']
            )));
        }

        return array_values($grouped);
    }

    private function error(int $row, int $column, string $message): array
    {
        return [
            'row' => $row,
            'column' => $column,
            'messages' => [$message],
        ];
    }
}
// ########### FIN CAMBIO VALIDACIÓN PREVIA IMPORTACIÓN ITEMS
