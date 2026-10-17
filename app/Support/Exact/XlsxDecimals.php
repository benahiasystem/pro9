<?php

namespace App\Support\Exact;

use PhpOffice\PhpSpreadsheet\Cell\{Coordinate, DataType};
use PhpOffice\PhpSpreadsheet\Spreadsheet;

// ######## INICIO CALCULOS EXACTOS PRO9 ########
/** Restore numeric business cells from OOXML text before validation or arithmetic. */
final class XlsxDecimals
{
    public static function restore(Spreadsheet $book, string $path, array $columns, int $sheetIndex = 0): void
    {
        $zip=new \ZipArchive();
        if ($zip->open($path)!==true) throw new \InvalidArgumentException('El archivo XLSX no puede leerse.');
        try {
            $workbook=self::xml($zip,'xl/workbook.xml');
            $relationships=self::xml($zip,'xl/_rels/workbook.xml.rels');
            $sheets=$workbook->xpath('//*[local-name()="sheets"]/*[local-name()="sheet"]');
            $sheet=$sheets[$sheetIndex] ?? null;
            if (!$sheet) throw new \InvalidArgumentException('Hoja XLSX inexistente.');
            $id=(string)$sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')->id;
            $target=null;
            foreach ($relationships->xpath('//*[local-name()="Relationship"]') as $relationship) {
                if ((string)$relationship['Id']===$id && (string)$relationship['TargetMode']!=='External') $target=(string)$relationship['Target'];
            }
            if ($target===null) throw new \InvalidArgumentException('La hoja XLSX no tiene un destino interno.');
            $parts=[];
            foreach (explode('/',str_starts_with($target,'/') ? $target : 'xl/'.$target) as $part) {
                if ($part==='..') array_pop($parts);
                elseif ($part!=='' && $part!=='.') $parts[]=$part;
            }
            $data=self::xml($zip,implode('/',$parts));
            $allowed=array_fill_keys($columns,true);
            foreach ($data->xpath('//*[local-name()="sheetData"]/*[local-name()="row"]/*[local-name()="c"]') as $cell) {
                $coordinate=(string)$cell['r'];
                if (!preg_match('/^([A-Z]+)([1-9][0-9]*)$/D',$coordinate,$match) || !isset($allowed[Coordinate::columnIndexFromString($match[1])-1])) continue;
                if ((string)$cell['t']!=='' && (string)$cell['t']!=='n') continue;
                if ($cell->xpath('./*[local-name()="f"]')) continue; // Formulas remain visible to the validator.
                $values=$cell->xpath('./*[local-name()="v"]');
                if (!$values) continue;
                $text=(string)$values[0];
                if (strlen($text)>128 || !preg_match('/^[+-]?[0-9]+(?:\.[0-9]*)?(?:[Ee][+-]?[0-9]{1,3})?$/D',$text)) throw new \InvalidArgumentException('Valor decimal XLSX inválido en '.$coordinate);
                try {$text=(string)\Brick\Math\BigDecimal::of($text);}catch(\Throwable $error){throw new \InvalidArgumentException('Valor decimal XLSX inválido en '.$coordinate,0,$error);}
                $book->getSheet($sheetIndex)->getCell($coordinate)->setValueExplicit($text,DataType::TYPE_STRING);
            }
        } finally {$zip->close();}
    }

    private static function xml(\ZipArchive $zip, string $part): \SimpleXMLElement
    {
        $text=$zip->getFromName($part);
        if ($text===false || stripos($text,'<!DOCTYPE')!==false || stripos($text,'<!ENTITY')!==false) throw new \InvalidArgumentException('Estructura XLSX inválida.');
        return new \SimpleXMLElement($text,LIBXML_NONET);
    }
}
// ######## FIN CALCULOS EXACTOS PRO9 ########
