<?php
// ######## INICIO AUDITORIA CALCULOS EXACTOS PRO9 ########
function exactAuditClassify(array $rows, string $root): array
{
    $path=$root.'/config/exact_rounding_registry.json';
    $registry=is_file($path) ? json_decode(file_get_contents($path),true,512,JSON_THROW_ON_ERROR) : [];
    $seen=[];$evidenceCache=[];
    foreach ($rows as &$row) {
        $key=$row['file'].'|'.$row['symbol'].'|'.$row['operation'].'|'.$row['expression'];
        $ordinal=$seen[$key] ?? 0;$seen[$key]=$ordinal+1;
        $row['id']=substr(hash('sha256',$key.'|'.$ordinal),0,24);
        $review=$registry[$row['id']] ?? null;
        $valid=exactAuditValidReview($review,$root,$evidenceCache)
            && $review['source']['file'] === $row['file'] && $review['source']['symbol'] === $row['symbol']
            && $review['source']['operation'] === $row['operation'] && $review['source']['operation_language'] === 'php'
            && (!isset($review['proof']) || ($row['technical_proof'] ?? null) === $review['proof']);
        $row['classification']=$valid ? $review : ['category'=>'pending','justification'=>null,'evidence'=>[]];
        $row['status']=$valid ? 'justified' : 'pending';
    }
    unset($row);
    usort($rows,fn($a,$b)=>[$a['file'],$a['line'],$a['operation'],$a['id']]<=>[$b['file'],$b['line'],$b['operation'],$b['id']]);
    return $rows;
}

function exactAuditValidReview($review, string $root, ?array &$contents = null): bool
{
    $contents ??= [];
    if (!is_array($review) || !in_array($review['category'] ?? '',['storage','presentation','settlement','external_protocol','allocation','discrete_units','technical','unreachable','validation','exact_arithmetic'],true)
        || !is_string($review['justification'] ?? null) || trim($review['justification'])==='' || !is_array($review['evidence'] ?? null) || !$review['evidence']) return false;
    if (!is_array($review['source'] ?? null) || !in_array($review['source']['operation_language'] ?? null, ['php','javascript'], true)) return false;
    foreach (['file','symbol','operation'] as $field) if (!is_string($review['source'][$field] ?? null) || trim($review['source'][$field]) === '') return false;
    foreach ($review['evidence'] as $evidence) {
        if (!is_string($evidence)) return false;
        $file=explode('#',$evidence,2)[0];
        $path=realpath($root.'/'.$file);
        if (!$path || !str_starts_with($path,realpath($root).DIRECTORY_SEPARATOR) || !is_file($path)) return false;
        $stamp = $path;
        $contents[$stamp] ??= ['hash'=>hash_file('sha256', $path), 'text'=>file_get_contents($path)];
        if (($review['evidence_sha256'][$file] ?? null) !== $contents[$stamp]['hash']) return false;
        $anchor = explode('#', $evidence, 2)[1] ?? null;
        if ($anchor !== null && !str_contains($contents[$stamp]['text'], $anchor)) return false;
    }
    return true;
}

function exactAuditRegistryDiagnostics(array $rows, string $root, string $language): array
{
    $path = $root.'/config/exact_rounding_registry.json';
    $registry = is_file($path) ? json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR) : [];
    $ids = array_fill_keys(array_column($rows, 'id'), true);
    $current = array_column($rows, null, 'id');
    $errors = [];
    foreach ($registry as $id=>$review) {
        if (empty($review['source']['file'])) { $errors[] = ['id'=>$id, 'reason'=>'missing_source']; continue; }
        if (($review['source']['operation_language'] ?? null) === $language && !isset($ids[$id])) {
            $errors[] = ['id'=>$id, 'reason'=>'orphaned_review', 'source'=>$review['source']];
        } elseif (($review['source']['operation_language'] ?? null) === $language && ($current[$id]['status'] ?? null) === 'pending') {
            $errors[] = ['id'=>$id, 'reason'=>'invalid_or_stale_review', 'source'=>$review['source']];
        }
    }
    return $errors;
}
// ######## FIN AUDITORIA CALCULOS EXACTOS PRO9 ########
