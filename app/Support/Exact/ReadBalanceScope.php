<?php
namespace App\Support\Exact;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Reuse exact batch operands only while rendering these same model instances. */
final class ReadBalanceScope
{
    private static ?\WeakMap $balances=null;
    public static function value($document): ?\Brick\Math\BigRational {return self::$balances[$document] ?? null;}
    public static function run($documents,callable $render)
    {
        self::$balances ??= new \WeakMap();$previous=[];
        try {
        foreach (array_chunk($documents->all(),500) as $chunk) {
            $balances=Receivables::balancesForDocuments($chunk);
            foreach ($chunk as $document) {$previous[]=[$document,self::$balances[$document] ?? null];self::$balances[$document]=$balances[$document->id];}
        }
        return $render();}
        finally {foreach ($previous as [$document,$value]) {if ($value===null) unset(self::$balances[$document]);else self::$balances[$document]=$value;}}
    }
}
// ######### FIN CAMBIO POSTGRESQL 17
