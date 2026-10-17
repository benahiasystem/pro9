<?php
// ######## INICIO AUDITORIA CALCULOS EXACTOS PRO9 ########
function exactAuditSourceExcluded(string $file, array $policy): bool
{
    foreach (explode('/', $file) as $component) if (in_array($component, $policy['excluded_directory_names'], true)) return true;
    foreach ($policy['exclusions'] as $exclusion) {
        if (!is_string($exclusion['reason'] ?? null) || trim($exclusion['reason']) === '') throw new RuntimeException('Exclusión sin justificación.');
        if ($file === $exclusion['path'] || str_starts_with($file, $exclusion['path'].'/')) return true;
    }
    return false;
}

function exactAuditSourceFiles(string $root, array $policy, string $language): array
{
    $result = [];
    foreach ($policy['roots'] as $directory) {
        if (!is_dir($root.'/'.$directory)) throw new RuntimeException('Raíz de auditoría ausente: '.$directory);
        $filter = new RecursiveCallbackFilterIterator(new RecursiveDirectoryIterator($root.'/'.$directory, FilesystemIterator::SKIP_DOTS),
            fn($entry)=>!exactAuditSourceExcluded(str_replace('\\', '/', substr($entry->getPathname(), strlen($root)+1)), $policy));
        foreach (new RecursiveIteratorIterator($filter) as $file) {
            $path = str_replace('\\', '/', substr($file->getPathname(), strlen($root)+1));
            if ($language === 'php' ? str_ends_with($path, '.php') : preg_match('/\.(?:js|vue|ts|tsx)$/D', $path) || str_ends_with($path, '.blade.php')) $result[] = $path;
        }
    }
    sort($result);
    return $result;
}

function exactAuditMissingSources(array $expected, array $scanned): array
{
    return array_values(array_diff($expected, $scanned));
}
// ######## FIN AUDITORIA CALCULOS EXACTOS PRO9 ########
