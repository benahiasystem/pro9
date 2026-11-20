<?php
declare(strict_types=1);

// Child process only: do not change the identity of the PHPUnit process.
require dirname(__DIR__, 2).'/vendor/autoload.php';

use App\Database\PostgresBackup;
use App\Database\PostgresBootstrap;

try {
    if (getenv('SAB_POSTGRES_TESTS') !== '1' || count($argv) !== 3) {
        throw new RuntimeException('This probe requires the isolated PostgreSQL test runner.');
    }
    [$script, $source, $destination] = $argv;
    $root = realpath(dirname($source));
    if (!$root || !str_starts_with($root, sys_get_temp_dir().'/sab-backup-failure-')
        || dirname(dirname($destination)) !== $root) {
        throw new RuntimeException('Only this probe\'s temporary files are allowed.');
    }
    if (!function_exists('posix_setuid') || !function_exists('posix_setgid') || posix_geteuid() !== 0) {
        throw new RuntimeException('The private test container must support a root-to-unprivileged child.');
    }
    class_exists(PostgresBackup::class);
    class_exists(PostgresBootstrap::class);
    if (!posix_setgid(65534) || !posix_setuid(65534) || posix_geteuid() !== 65534) {
        throw new RuntimeException('The child could not drop its privileges.');
    }
    setlocale(LC_ALL, 'C');
    $warnings = [];
    set_error_handler(static function (int $severity, string $message, string $file, int $line) use (&$warnings): bool {
        if ($severity === E_WARNING && (str_contains($message, 'ZipArchive::open()') || str_contains($message, 'ZipArchive::close()')
            || (str_contains($message, 'fopen(') && str_contains($message, 'Permission denied')))) {
            $warnings[] = $message;
            return true;
        }
        throw new ErrorException($message, 0, $severity, $file, $line);
    });
    try {
        $probe = fopen($destination.'.access-check', 'xb');
        if ($probe !== false) {
            fclose($probe);
            unlink($destination.'.access-check');
            throw new RuntimeException('The unprivileged child unexpectedly created a probe file.');
        }
        $permissionDenied = str_contains(implode("\n", $warnings), 'Permission denied');
        if (!$permissionDenied) throw new RuntimeException('The operating system did not report EACCES.');
        (new PostgresBackup())->pack(['sab_temporary_backup_probe' => $source], [], $destination);
        throw new RuntimeException('The unprivileged child unexpectedly published a package.');
    } catch (RuntimeException $error) {
        $stage = match ($error->getMessage()) {
            'No se pudo crear el ZIP.' => 'open',
            'No se pudo cerrar el ZIP; compruebe el espacio disponible.' => 'close',
            default => throw $error,
        };
        echo json_encode(['uid' => posix_geteuid(), 'permission_denied' => $permissionDenied,
            'package_failure_stage' => $stage,
            'source_readable' => is_readable($source), 'directory_searchable' => is_executable(dirname($destination)),
            'directory_writable' => is_writable(dirname($destination)), 'published' => file_exists($destination),
            'warnings' => $warnings], JSON_THROW_ON_ERROR).PHP_EOL;
    } finally {
        restore_error_handler();
    }
} catch (Throwable $error) {
    // Deliberately omit environment, credentials and exception traces.
    fwrite(STDERR, $error->getMessage().PHP_EOL);
    exit(1);
}
