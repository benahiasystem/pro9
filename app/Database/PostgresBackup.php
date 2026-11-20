<?php

namespace App\Database;

use Symfony\Component\Process\Process;

// ########## INICIO CAMBIO POSTGRESQL 17
final class PostgresBackup
{
    private function process(string $tool, array $config, array $arguments): Process
    {
        PostgresBootstrap::identifier($config['database']);
        $process = new Process(array_merge(['/usr/lib/postgresql/17/bin/'.$tool], $arguments), null, [
            'PGHOST' => (string) $config['host'], 'PGPORT' => (string) ($config['port'] ?? 5432),
            'PGDATABASE' => $config['database'], 'PGUSER' => $config['username'],
            'PGPASSWORD' => $config['password'], 'PGSSLMODE' => $config['sslmode'] ?? 'prefer',
            'PGCLIENTENCODING' => 'UTF8', 'PGOPTIONS' => '-c timezone=America/Caracas',
        ]);
        $process->setTimeout(3600);
        return $process;
    }
    private function execute(Process $process): void
    {
        $process->run();
        if (!$process->isSuccessful()) throw new \RuntimeException('Falló la herramienta PostgreSQL: '.trim($process->getErrorOutput()));
    }
    public function dump(array $config, string $path): void
    {
        $this->assertVersion($config);
        if (!is_dir(dirname($path))) mkdir(dirname($path), 0700, true);
        $partial = $path.'.partial-'.bin2hex(random_bytes(6));
        try {
            $this->execute($this->process('pg_dump', $config, ['--format=custom', '--no-owner', '--no-acl', '--file='.$partial]));
            chmod($partial, 0600);
            if (!rename($partial, $path)) throw new \RuntimeException('No se pudo publicar el respaldo.');
        } finally { if (is_file($partial)) unlink($partial); }
    }
    /** Restore only into an independently provisioned, empty database. */
    public function restore(array $config, string $path): void
    {
        if (!is_file($path) || file_get_contents($path, false, null, 0, 5) !== 'PGDMP') throw new \InvalidArgumentException('Respaldo PostgreSQL incompleto o incompatible.');
        $this->assertVersion($config);
        $pdo = new \PDO('pgsql:host='.$config['host'].';port='.($config['port'] ?? 5432).';dbname='.$config['database'], $config['username'], $config['password'], [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
        if ($pdo->query("SELECT count(*) FROM pg_tables WHERE schemaname='public'")->fetchColumn() != 0) throw new \LogicException('La restauración requiere una base independiente vacía.');
        $pdo = null;
        // Supabase owns managed extensions even when a tenant may install them.
        // Keep all application comments, excluding only comments on managed extensions.
        $list = $this->process('pg_restore', $config, ['--list', $path]);
        $this->execute($list);
        $toc = tempnam(sys_get_temp_dir(), 'pro9-pg-toc-');
        try {
            $lines = array_filter(explode("\n", $list->getOutput()), static fn ($line) => !preg_match('/\bCOMMENT\s+-\s+EXTENSION\s+/', $line));
            file_put_contents($toc, implode("\n", $lines)."\n"); chmod($toc, 0600);
            $this->execute($this->process('pg_restore', $config, ['--dbname='.$config['database'], '--exit-on-error', '--single-transaction', '--no-owner', '--no-acl', '--use-list='.$toc, $path]));
        } finally { if (is_file($toc)) unlink($toc); }
    }
    public function requireTables(array $config,string $path,array $tables): void
    {
        $list=$this->process('pg_restore',$config,['--list',$path]);$this->execute($list);
        foreach ($tables as $table) {
            PostgresBootstrap::identifier($table);
            if (!preg_match('/\\bTABLE public '.preg_quote($table,'/').'\\s/',$list->getOutput())) throw new \InvalidArgumentException('El respaldo no contiene la tabla requerida '.$table.'.');
        }
    }
    private function assertVersion(array $config): void
    {
        $pdo = new \PDO('pgsql:host='.$config['host'].';port='.($config['port'] ?? 5432).';dbname='.$config['database'], $config['username'], $config['password'], [\PDO::ATTR_ERRMODE=>\PDO::ERRMODE_EXCEPTION]);
        if ((int)$pdo->query('SHOW server_version_num')->fetchColumn() !== 170011) throw new \RuntimeException('El respaldo requiere servidor PostgreSQL 17.11.');
    }
    /** Extract only manifest-verified entries to a new private directory. */
    public function extract(string $package, string $prefix, string $directory): void
    {
        $manifest = $this->verify($package);
        if (file_exists($directory)) throw new \LogicException('El directorio de restauración debe ser nuevo.');
        if (!mkdir($directory, 0700, true)) throw new \RuntimeException('No se pudo crear el directorio privado.');
        $zip = new \ZipArchive(); $zip->open($package, \ZipArchive::RDONLY);
        try {
            foreach ($manifest['files'] as $entry=>$metadata) {
                if (!str_starts_with($entry, $prefix)) continue;
                $relative=substr($entry,strlen($prefix)); self::entry($relative);
                $target=$directory.'/'.$relative;
                if (!is_dir(dirname($target)) && !mkdir(dirname($target),0700,true)) throw new \RuntimeException('No se pudo crear el directorio de archivos.');
                $input=$zip->getStream($entry); $output=fopen($target,'xb');
                if (!$input || !$output) throw new \RuntimeException('No se pudo extraer un archivo.');
                try { $size=stream_copy_to_stream($input,$output); }
                finally { fclose($input); fclose($output); }
                chmod($target,0600);
                if ($size !== $metadata['size'] || !hash_equals($metadata['sha256'],hash_file('sha256',$target))) throw new \RuntimeException('La extracción está incompleta.');
            }
        } finally { $zip->close(); }
    }
    public function pack(array $dumps, array $files, string $destination, array $metadata = []): array
    {
        if (!$dumps) throw new \InvalidArgumentException('El respaldo debe contener al menos una base.');
        if (!is_dir(dirname($destination))) mkdir(dirname($destination),0700,true);
        $manifest = array_merge($metadata, ['format_version' => 1, 'engine' => 'postgresql', 'server_version' => '17.11', 'created_at' => gmdate('c'), 'databases' => array_keys($dumps), 'files' => []]);
        $partial = $destination.'.partial-'.bin2hex(random_bytes(6));
        $zip = new \ZipArchive();
        try {
            if ($zip->open($partial, \ZipArchive::CREATE | \ZipArchive::EXCL) !== true) throw new \RuntimeException('No se pudo crear el ZIP.');
            foreach ($dumps as $database => $path) {
                PostgresBootstrap::identifier($database);
                $files['databases/'.$database.'.dump'] = $path;
            }
            foreach ($files as $entry => $path) {
                self::entry($entry);
                if (!is_file($path) || is_link($path)) throw new \InvalidArgumentException('Archivo de respaldo no válido.');
                $manifest['files'][$entry] = ['sha256' => hash_file('sha256', $path), 'size' => filesize($path)];
                if (!$zip->addFile($path, $entry)) throw new \RuntimeException('No se pudo añadir un archivo al ZIP.');
            }
            $zip->addFromString('manifest.json', json_encode($manifest, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            if (!$zip->close()) throw new \RuntimeException('No se pudo cerrar el ZIP; compruebe el espacio disponible.');
            if (!rename($partial, $destination)) throw new \RuntimeException('No se pudo publicar el ZIP.');
            chmod($destination, 0600);
            return $manifest;
        } finally { if (is_file($partial)) unlink($partial); }
    }
    public function verify(string $path): array
    {
        $zip = new \ZipArchive();
        if ($zip->open($path, \ZipArchive::RDONLY) !== true) throw new \InvalidArgumentException('ZIP de respaldo inválido.');
        try {
            $manifest = json_decode($zip->getFromName('manifest.json') ?: '', true, 512, JSON_THROW_ON_ERROR);
            if (($manifest['format_version'] ?? null) !== 1 || ($manifest['engine'] ?? null) !== 'postgresql' || ($manifest['server_version'] ?? null) !== '17.11') throw new \InvalidArgumentException('Formato o versión incompatible.');
            if (empty($manifest['databases']) || !is_array($manifest['files'] ?? null)) throw new \InvalidArgumentException('Manifiesto incompleto.');
            foreach ($manifest['databases'] as $database) {
                PostgresBootstrap::identifier($database);
                if (!isset($manifest['files']['databases/'.$database.'.dump'])) throw new \InvalidArgumentException('Falta una base declarada.');
            }
            if ($zip->numFiles !== count($manifest['files']) + 1) throw new \InvalidArgumentException('El ZIP contiene archivos no declarados.');
            foreach ($manifest['files'] as $entry => $expected) {
                self::entry($entry);
                $stream = $zip->getStream($entry);
                if (!$stream) throw new \InvalidArgumentException('Falta un archivo del respaldo.');
                $hash = hash_init('sha256'); $size = hash_update_stream($hash, $stream); fclose($stream);
                if ($size !== $expected['size'] || !hash_equals($expected['sha256'], hash_final($hash))) throw new \InvalidArgumentException('El respaldo está incompleto o su checksum es inválido.');
            }
            return $manifest;
        } finally { $zip->close(); }
    }
    private static function entry(string $entry): void
    {
        if ($entry === '' || str_contains($entry, '\\') || str_contains($entry, "\0") || str_starts_with($entry, '/') || preg_match('~(?:^|/)\.\.(?:/|$)~', $entry)) throw new \InvalidArgumentException('Ruta ZIP no permitida.');
    }
}
// ######### FIN CAMBIO POSTGRESQL 17
