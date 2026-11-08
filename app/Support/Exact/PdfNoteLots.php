<?php

namespace App\Support\Exact;

// ######## INICIO LOTES CONGELADOS EN NOTAS PDF ########
final class PdfNoteLots
{
    public static function codes($line): array
    {
        $selected=data_get($line,'item.IdLoteSelected');
        if (!is_array($selected)) return [];
        $codes=[];
        foreach ($selected as $lot) {
            $code=trim((string)data_get($lot,'code',''));
            if ($code !== '') $codes[]=$code;
        }
        return $codes;
    }

    public static function ticketExtraHeight(iterable $items): int
    {
        $chars=20; $lines=0;
        foreach ($items as $item) foreach (self::codes($item) as $code) {
            $lines+=(int)ceil((mb_strlen($code)+6)/$chars);
        }
        return $lines*5;
    }
}
// ######## FIN LOTES CONGELADOS EN NOTAS PDF ########
