<?php

// ########### INICIO CAMBIO VALIDACIÓN PREVIA IMPORTACIÓN ITEMS
namespace App\Support\ItemImport;

class ItemImportValidationResult
{
    /** @var array */
    private $errors;

    /** @var int */
    private $totalRows;

    public function __construct(array $errors, int $totalRows)
    {
        $this->errors = array_values($errors);
        $this->totalRows = $totalRows;
    }

    public function passes(): bool
    {
        return count($this->errors) === 0;
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function totalRows(): int
    {
        return $this->totalRows;
    }
}
// ########### FIN CAMBIO VALIDACIÓN PREVIA IMPORTACIÓN ITEMS
