<?php

// Read-only inventory. Comments are excluded; each match requires a consumer/boundary review.
$root=dirname(__DIR__);
$inventory=require $root.'/config/exact_calculations.php';
$matches=[];
foreach (['app','modules'] as $directory) {
    $files=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$directory,FilesystemIterator::SKIP_DOTS));
    foreach ($files as $file) {
        if ($file->getExtension() !== 'php' || str_contains($file->getPathname(),'/tests/')) continue;
        $tokens=token_get_all(file_get_contents($file->getPathname()));
        foreach ($tokens as $index=>$token) {
            if (!is_array($token)) continue;
            if ($token[0] === T_DOUBLE_CAST) $operation='float';
            elseif ($token[0] === T_STRING && in_array(strtolower($token[1]),['round','number_format','floatval'],true)) {
                $next=$index+1;while(isset($tokens[$next]) && is_array($tokens[$next]) && $tokens[$next][0] === T_WHITESPACE) $next++;
                if (($tokens[$next] ?? null) !== '(') continue;
                $operation=strtolower($token[1]);
            } else continue;
            $path=substr($file->getPathname(),strlen($root)+1);
            $matches[]=['file'=>$path,'line'=>$token[2],'operation'=>$operation,
                'review'=>str_contains($path,'/Templates/pdf/') || str_contains($path,'/Resources/') ? 'presentation_or_consumer_review' : 'business_consumer_review'];
        }
    }
}
usort($matches,fn($a,$b)=>[$a['file'],$a['line']] <=> [$b['file'],$b['line']]);
echo json_encode(['contract_version'=>1,'tables'=>count($inventory),'columns'=>array_sum(array_map('count',$inventory)),
    'decimal_columns'=>$inventory,'php_review'=>$matches],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL;
