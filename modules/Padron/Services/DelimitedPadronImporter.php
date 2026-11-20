<?php
namespace Modules\Padron\Services;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Stream the legacy pipe-delimited catalog without server-side file privileges. */
final class DelimitedPadronImporter
{
    public function import(\Illuminate\Database\Connection $db,string $file): int
    {
        $input=fopen($file,'rb');
        if (!$input) throw new \RuntimeException('No se pudo abrir el catálogo local.');
        $columns=['ruc','nombre_razon_social','estado_contribuyente','condicion_domicilio','ubigeo','tipo_via','nombre_via','codigo_zona','tipo_zona','numero','interior','lote','departamento','manzana','kilometro'];
        try {
            $header=fgets($input);
            if ($header===false || strtoupper(trim(explode('|',$header)[0],"\xEF\xBB\xBF \t\r\n"))!=='RUC') throw new \InvalidArgumentException('El catálogo no tiene el encabezado esperado.');
            return $db->transaction(function () use ($db,$input,$columns) {
                $db->statement('TRUNCATE TABLE padrones RESTART IDENTITY');$rows=[];$count=0;
                while (($line=fgets($input))!==false) {
                    if (trim($line)==='') continue;
                    $values=explode('|',rtrim($line,"\r\n"));
                    if (count($values)<count($columns)) throw new \InvalidArgumentException('El catálogo contiene una fila incompleta.');
                    $row=array_combine($columns,array_slice($values,0,count($columns)));
                    $row+=['status'=>1,'created_at'=>now(),'updated_at'=>null];$rows[]=$row;$count++;
                    if (count($rows)===500) {$db->table('padrones')->insert($rows);$rows=[];}
                }
                if (!feof($input)) throw new \RuntimeException('No se pudo terminar de leer el catálogo.');
                if (!$count) throw new \InvalidArgumentException('El catálogo no contiene registros.');
                if ($rows) $db->table('padrones')->insert($rows);return $count;
            });
        } finally {fclose($input);}
    }
}
// ######### FIN CAMBIO POSTGRESQL 17
