<?php
declare(strict_types=1);

/** Filtering acceptance never filters scanning, IDs or registry diagnostics. */
function exactAuditScopeArgument(array $arguments): ?string
{
    $scopes=array_values(array_filter($arguments,static fn($arg)=>str_starts_with($arg,'--scope=')));
    if (!$scopes) return null;
    if (count($scopes)!==1 || $scopes[0]!=='--scope=billing') throw new InvalidArgumentException('Alcance de auditoría desconocido o duplicado.');
    return 'billing';
}

function exactAuditScopePolicy(string $root,string $scope): array
{
    $policy=json_decode(file_get_contents($root.'/config/exact_audit_scopes.json'),true,512,JSON_THROW_ON_ERROR);
    $entry=$policy[$scope] ?? null;
    if (($policy['version'] ?? null)!==1 || $scope!=='billing' || !is_array($entry)
        || empty($entry['patterns']) || empty($entry['required_files']) || !is_string($entry['decision'] ?? null)) {
        throw new RuntimeException('El inventario de facturación está vacío o es inválido.');
    }
    $excluded = $entry['excluded_files'] ?? [];
    if (!is_array($excluded)) throw new RuntimeException('La lista de fuentes excluidas es inválida.');
    foreach (array_merge($entry['patterns'],$entry['required_files'],$excluded) as $path) {
        if (!is_string($path) || $path==='' || str_starts_with($path,'/') || str_contains($path,'..') || str_contains($path,"\0") || str_contains($path,'\\')) {
            throw new RuntimeException('Ruta de alcance inválida.');
        }
    }
    foreach ($excluded as $file) {
        if (str_contains($file, '*')) throw new RuntimeException('Las exclusiones requieren una ruta de archivo exacta.');
    }
    foreach ($entry['required_files'] as $file) {
        if (!is_file($root.'/'.$file) || !exactAuditScopeContains($file,$entry)) throw new RuntimeException('Falta una fuente obligatoria de facturación: '.$file);
    }
    $excludedMethods = $entry['excluded_methods'] ?? [];
    if (!is_array($excludedMethods)) throw new RuntimeException('La lista de métodos excluidos es inválida.');
    foreach ($excludedMethods as $file => $methods) {
        if (!in_array($file, $entry['required_files'], true) || !is_array($methods) || !array_is_list($methods) || !$methods) {
            throw new RuntimeException('Los métodos excluidos requieren una fuente obligatoria y una lista de nombres exactos.');
        }
        foreach ($methods as $method) {
            if (!is_string($method) || !preg_match('/^[A-Za-z_]\w*::[A-Za-z_]\w*$/D', $method)) {
                throw new RuntimeException('Nombre de método excluido inválido.');
            }
        }
    }
    return $entry;
}

function exactAuditScopeContains(string $file,array $policy): bool
{
    if (in_array($file, $policy['excluded_files'] ?? [], true)) return false;
    foreach ($policy['patterns'] as $pattern) {
        $regex=str_replace(['\\*\\*','\\*'],['.*','[^/]*'],preg_quote($pattern,'~'));
        if (preg_match('~^'.$regex.'$~D',$file)) return true;
    }
    return false;
}

function exactAuditScopeSummary(array $rows,array $registryErrors,array $scannedFiles,array $policy): array
{
    $selected=array_values(array_filter($rows,static fn($row)=>exactAuditScopeContains($row['file'],$policy)
        && !in_array($row['symbol'] ?? null, $policy['excluded_methods'][$row['file']] ?? [], true)));
    $pending=count(array_filter($selected,static fn($row)=>$row['status']==='pending'));
    // A malformed diagnostic cannot be silently assigned outside billing.
    $errors=array_values(array_filter($registryErrors,static fn($error)=>empty($error['source']['file']) || exactAuditScopeContains($error['source']['file'],$policy)));
    $files=array_values(array_filter($scannedFiles,static fn($file)=>exactAuditScopeContains($file,$policy)));
    if (!$files) throw new RuntimeException('No se escanearon fuentes del alcance obligatorio.');
    return ['name'=>'billing','decision'=>$policy['decision'],'scanned_files'=>$files,'candidates'=>count($selected),'pending'=>$pending,
        'pending_outside_scope'=>count(array_filter($rows,static fn($row)=>$row['status']==='pending'))-$pending,
        'registry_errors'=>$errors,'registry_errors_outside_scope'=>count($registryErrors)-count($errors),
        'shared_dependencies'=>$policy['shared_dependencies'] ?? ''];
}
