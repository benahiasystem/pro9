<?php
// ########## INICIO CAMBIO POSTGRESQL 17
$root=dirname(__DIR__);$files=[];$failures=[];
foreach (['app','modules','database','config','tests','scripts'] as $directory) {
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$directory,FilesystemIterator::SKIP_DOTS)) as $file) if ($file->getExtension()==='php') $files[]=$file->getPathname();
}
foreach ($files as $file) {
    $p=proc_open([PHP_BINARY,'-l',$file],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);fclose($pipes[0]);$out=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
    if (proc_close($p)!==0) $failures[]=substr($file,strlen($root)+1).': '.$out.$error;
}
foreach ($failures as $failure) fwrite(STDERR,$failure."\n");echo json_encode(['php_version'=>PHP_VERSION,'files'=>count($files),'errors'=>count($failures)])."\n";exit($failures?1:0);
// ######### FIN CAMBIO POSTGRESQL 17
