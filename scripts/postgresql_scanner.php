<?php
declare(strict_types=1);

/** Public antivirus definitions only; no application database or .env is read. */
function postgresqlPrepareScanner(string $destination, string $report, array $environment): void
{
    $image = 'clamav/clamav@sha256:45fe5149fbee5a46509c9848ef20d0c354b7b2323c9369f285939a37cf33422a';
    $home = $environment['XDG_CACHE_HOME'] ?? (($environment['HOME'] ?? '').'/.cache');
    if ($home === '' || !str_starts_with($home, '/')) throw new RuntimeException('Falta un caché absoluto para las firmas públicas.');
    $cache = $home.'/pro9-postgresql-clamav-17-11';
    if (is_link($cache) || is_link($destination)) throw new RuntimeException('El caché de análisis no admite enlaces simbólicos.');
    if (!is_dir($cache) && !mkdir($cache, 0755, true)) throw new RuntimeException('No se pudo preparar el caché de firmas.');
    $lock = fopen($cache.'.lock', 'c');
    if (!$lock || !flock($lock, LOCK_EX)) throw new RuntimeException('No se pudo bloquear la actualización de firmas.');
    try {
        $freshDefinitions = static function () use ($cache): bool {
            foreach (glob($cache.'/*') as $file) {
                if (is_link($file)) throw new RuntimeException('Las firmas públicas no admiten enlaces simbólicos.');
            }
            $fresh = false;
            foreach (['daily.cld', 'daily.cvd'] as $name) {
                $file = $cache.'/'.$name;
                if (!is_file($file)) continue;
                $header = file_get_contents($file, false, null, 0, 512);
                $parts = explode(':', trim((string) $header));
                $stamp = end($parts);
                if (str_starts_with((string) $header, 'ClamAV-VDB:') && ctype_digit($stamp)) {
                    $age = time() - (int) $stamp;
                    $fresh = $fresh || ($age >= -86400 && $age <= 144 * 3600);
                }
            }
            return $fresh;
        };
        $fresh = $freshDefinitions();
        if (!$fresh) {
            $updater=$environment['PRO9_POSTGRES_AV_UPDATER'] ?? '';
            $run=$environment['PRO9_POSTGRES_RUN_ID'] ?? '';
            if (!preg_match('/^pro9-pgtest-[a-f0-9]{12}-av-update$/D',$updater) || $updater!==$run.'-av-update') throw new RuntimeException('Falta la identidad privada del actualizador.');
            $command = ['docker', 'run', '--rm', '--name', $updater,'--label','pro9.postgresql.scanner-run='.$run,
                '--entrypoint', 'sh', '--mount', 'type=bind,src='.$cache.',dst=/definitions', $image, '-c',
                'cp -n /var/lib/clamav/*.cvd* /definitions/; chown clamav:clamav /definitions; find /definitions -maxdepth 1 -type f -exec chown clamav:clamav {} +; exec freshclam --datadir=/definitions --stdout'];
            if (command($command, $environment, $report.'.update.log') !== 0) {
                throw new RuntimeException('No se pudieron actualizar las firmas públicas de ClamAV.');
            }
        }
        if (!$freshDefinitions()) throw new RuntimeException('La actualización no produjo firmas públicas vigentes.');
        if (!is_dir($destination) && !mkdir($destination, 0755, true)) throw new RuntimeException('No se pudo preparar el análisis temporal.');
        chmod($destination, 0755);
        $hashes = [];
        foreach (glob($cache.'/*') as $file) {
            if (is_link($file)) throw new RuntimeException('Las firmas públicas no admiten enlaces simbólicos.');
            if (!is_file($file) || !preg_match('/\.(?:cvd|cld|sign)$/D', $file)) continue;
            if (!copy($file, $destination.'/'.basename($file))) throw new RuntimeException('No se pudo copiar una firma pública.');
            chmod($destination.'/'.basename($file), 0644);
            $hashes[basename($file)] = hash_file('sha256', $file);
        }
        if (!$hashes) throw new RuntimeException('El análisis temporal no tiene firmas públicas.');
        ksort($hashes);
        file_put_contents($report, json_encode(['image' => $image, 'definitions_sha256' => $hashes,
            'cache_reused' => $fresh, 'scope' => 'Public vendor signatures copied to this private run; no tenant files or database data.'],
            JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL);
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}
