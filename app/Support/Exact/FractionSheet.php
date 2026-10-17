<?php

namespace App\Support\Exact;

use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

// ######## INICIO CALCULOS EXACTOS PRO9 ########
/** Exact text interchange: Excel's numeric cells cannot preserve arbitrary fractions. */
final class FractionSheet
{
    public static function append(Worksheet $source, iterable $records, array $fields): void
    {
        $sheet=new Worksheet($source->getParent(),'Fracciones exactas');
        $source->getParent()->addSheet($sheet);
        $row=1;
        self::write($sheet,$row++,['Registro','Campo','Representación decimal (12)','Numerador','Denominador']);
        foreach ($records as $position=>$record) {
            $id=is_array($record) ? ($record['report_key'] ?? $record['id'] ?? $position) : ($record->report_key ?? $record->id ?? $position);
            foreach ($fields as $field) {
                $value=Decimal::readRequired($record,$field);
                $fraction=Decimal::fraction($value);
                self::write($sheet,$row++,[(string)$id,$field,Decimal::represent($value),$fraction['n'],$fraction['d']]);
            }
        }
        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:E'.($row-1));
        foreach (['A'=>18,'B'=>28,'C'=>38,'D'=>44,'E'=>44] as $column=>$width) $sheet->getColumnDimension($column)->setWidth($width);
    }

    private static function write(Worksheet $sheet, int $row, array $values): void
    {
        foreach (array_values($values) as $column=>$value) {
            // PhpSpreadsheet otherwise silently truncates strings at Excel's cell limit.
            if (mb_strlen((string)$value, 'UTF-8') > 32767) {
                throw new \LengthException('No se puede exportar esta fracción a Excel sin perder cifras.');
            }
            $sheet->setCellValueExplicitByColumnAndRow($column+1,$row,(string)$value,DataType::TYPE_STRING);
        }
    }
}
// ######## FIN CALCULOS EXACTOS PRO9 ########
