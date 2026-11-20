<?php
// ########## INICIO CAMBIO POSTGRESQL 17
/** Reject MySQL SQL in executable first-party PHP strings, preserving historical reports. */
declare(strict_types=1);
$root=dirname(__DIR__);$errors=[];$files=0;
$patterns=[
    '/\bUPDATE\s+[`"\w.]+(?:\s+(?:AS\s+)?\w+)?\s+(?:LEFT\s+|RIGHT\s+|INNER\s+)?JOIN\b/i',
    '/\b(?:SHOW\s+(?:FULL\s+)?TABLES|SHOW\s+COLUMNS|SHOW\s+INDEX|SHOW\s+CREATE\s+TABLE|FOREIGN_KEY_CHECKS|AUTO_INCREMENT|ENGINE\s*=)\b/i',
    '/\b(?:IFNULL|GROUP_CONCAT|JSON_EXTRACT|JSON_UNQUOTE|JSON_SEARCH|JSON_TABLE|DATE_FORMAT|DATEDIFF|FIELD|DATABASE|FIND_IN_SET|SUBSTRING_INDEX|TIMESTAMPDIFF|CURDATE|DATE_ADD|DATE_SUB|YEAR|MONTH|DAY|RAND|MATCH|AGAINST|CONVERT_TZ|STR_TO_DATE|DAYOFWEEK|WEEKDAY|LAST_DAY|UNIX_TIMESTAMP|FROM_UNIXTIME|ISNULL|ANY_VALUE|UTC_TIMESTAMP|JSON_CONTAINS)\s*\(/i',
    '/\b(?:LOAD\s+DATA|INSERT\s+IGNORE|REPLACE\s+INTO|ON\s+DUPLICATE\s+KEY)\b/i',
    '/\bALTER\s+(?:TABLE|USER)\s+`/i',
    '/\b(?:SQL_CALC_FOUND_ROWS|COLLATE\s+utf8\w*|LIMIT\s+\d+\s*,\s*\d+)\b/i',
    '/\b(?:SELECT|WHERE|HAVING|SET)\s+IF\s*\(/i',
    '/\bUPDATE\s+[\w".]+\s+(?:AS\s+)?(\w+)\s+SET\s+\1\./i',
];
foreach (['app','modules','config','database/migrations','database/seeders'] as $directory) {
    $iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$directory,FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if ($file->getExtension()!=='php' || str_contains($file->getPathname(),'/Templates/')) continue;
        $files++;
        foreach (token_get_all(file_get_contents($file->getPathname())) as $token) {
            if (!is_array($token) || !in_array($token[0],[T_CONSTANT_ENCAPSED_STRING,T_ENCAPSED_AND_WHITESPACE],true)) continue;
            foreach ($patterns as $pattern) if (preg_match($pattern,$token[1])) $errors[]=substr($file->getPathname(),strlen($root)+1).':'.$token[2].' SQL incompatible: '.substr(str_replace(["\n","\r"],' ',$token[1]),0,160);
        }
    }
}
$config=file_get_contents($root.'/config/database.php');
if (preg_match("/'driver'\\s*=>\\s*'mysql'/",$config)) $errors[]='Existe una conexión MySQL activa.';
if (str_contains(file_get_contents($root.'/composer.json'),'ifsnop/mysqldump')) $errors[]='Existe una dependencia de dump MySQL.';
if (count(glob($root.'/database/migrations/tenant/*.php'))!==351) $errors[]='Se esperan 350 migraciones consolidadas y una migración SellNow.';
foreach ($errors as $error) fwrite(STDERR,$error.PHP_EOL);
fwrite($errors?STDERR:STDOUT,json_encode(['files'=>$files,'errors'=>count($errors)],JSON_THROW_ON_ERROR).PHP_EOL);
exit($errors?1:0);
// ######### FIN CAMBIO POSTGRESQL 17
