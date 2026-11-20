<?php
// ########## INICIO CAMBIO POSTGRESQL 17
/** Hash the source actually tested, including uncommitted and newly created files. No database access. */
function postgresqlSourceManifest(string $root): array
{
    $read=static function (array $command) use ($root): string {
        $process=proc_open($command,[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,$root);
        if (!is_resource($process)) throw new RuntimeException('No se pudo identificar el código de las pruebas.');
        fclose($pipes[0]);$out=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
        if (proc_close($process)!==0) throw new RuntimeException('No se pudo identificar el código: '.$error);
        return $out;
    };
    $paths=explode("\0",$read(['git','ls-files','-z','--cached','--others','--exclude-standard']));
    $files=postgresqlSourceFiles($root,$paths);
    return ['revision'=>trim($read(['git','rev-parse','HEAD'])),'branch'=>trim($read(['git','branch','--show-current'])),
        'sha256'=>hash('sha256',json_encode($files,JSON_THROW_ON_ERROR)),'files'=>$files];
}

/** Fingerprint only source and fixed inputs; Git enumeration belongs to the host. */
function postgresqlSourceFiles(string $root,array $paths): array
{
    $files=[];
    $fixedInputs=array_map(static fn($name)=>'informes/evidencias/postgresql_17_implementacion/'.$name,
        ['esquema.json','referencia_mysql_temporal.json','referencia_rendimiento_mysql.json','referencia_rendimiento_multitenant_mysql.json','casts_exactos_efectivos.json']);
    foreach (array_unique($paths) as $path) {
        if (!preg_match('~^(?:app/|modules/|database/|config/|routes/|resources/|tests/|scripts/|docker/|\.github/|\.gitattributes$|public/build/|public/porto-ecommerce/assets/js/(?:cart|main)\.js$|vite\.config\.js$|modules_statuses\.json$|composer\.(?:json|lock)$|package(?:-lock)?\.json$|phpunit\.xml$|docker-compose[^/]*\.yml$)~',$path)
            && !in_array($path,$fixedInputs,true)) continue;
        $files[$path]=is_file($root.'/'.$path) ? hash_file('sha256',$root.'/'.$path) : null;
    }
    ksort($files);
    return $files;
}

/** Only the explicitly generated benchmark reference may change before tests start. */
function postgresqlSourceAfterReference(array $before,array $after,string $reference): array
{
    $expected=$before['files'];$actual=$after['files'];unset($expected[$reference],$actual[$reference]);
    if ($before['revision']!==$after['revision'] || $before['branch']!==$after['branch'] || $expected!==$actual) {
        throw new RuntimeException('Cambió el código u otra entrada durante la captura de referencia.');
    }
    return $after;
}
// ######### FIN CAMBIO POSTGRESQL 17
