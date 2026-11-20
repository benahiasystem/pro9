<?php
namespace Tests\Unit;

use App\Database\PostgresBackup;
use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

/** Real filesystem failures, confined to the private test container. */
final class PostgresBackupFailureTest extends \PHPUnit\Framework\TestCase
{
    private string $directory;
    private ?string $limitedDirectory = null;

    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('SAB_POSTGRES_TESTS') !== '1') {
            $this->markTestSkipped('Use scripts/test_postgresql.php with its private test mounts.');
        }
        $this->directory = sys_get_temp_dir().'/sab-backup-failure-'.bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->directory, 0755));
        file_put_contents($this->directory.'/source.dump', 'PGDMP'.random_bytes(2 * 1024 * 1024));
        chmod($this->directory.'/source.dump', 0644);
    }

    protected function tearDown(): void
    {
        try {
            if (isset($this->directory)) {
                if (is_dir($this->directory.'/restricted')) chmod($this->directory.'/restricted', 0700);
                (new Filesystem())->deleteDirectory($this->directory);
            }
            if ($this->limitedDirectory !== null) (new Filesystem())->deleteDirectory($this->limitedDirectory);
        } finally {
            parent::tearDown();
        }
    }

    public function test_full_temporary_filesystem_keeps_previous_package_and_removes_all_partials(): void
    {
        $mount = '/sab-backup-faults';
        self::assertDirectoryExists($mount, 'The test-only 1 MiB tmpfs is required.');
        $stat = new Process(['stat', '-f', '--format=%T %S %b', $mount]);
        $stat->mustRun();
        [$type, $blockSize, $blocks] = explode(' ', trim($stat->getOutput()));
        self::assertSame('tmpfs', $type);
        self::assertGreaterThanOrEqual(524288, (int) $blockSize * (int) $blocks);
        self::assertLessThanOrEqual(2 * 1024 * 1024, (int) $blockSize * (int) $blocks);
        self::assertSame([], array_values(array_diff(scandir($mount), ['.', '..'])), 'The private mount must start empty.');
        $this->limitedDirectory = $mount.'/case-'.bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->limitedDirectory, 0700));
        self::assertSame([], array_values(array_diff(scandir($this->limitedDirectory), ['.', '..'])));
        $destination = $this->limitedDirectory.'/previous.zip';
        $small = $this->directory.'/small.dump';
        file_put_contents($small, 'PGDMP-small-temporary-probe');
        $backup = new PostgresBackup();
        $backup->pack(['sab_temporary_backup_probe' => $small], [], $destination);
        $previous = hash_file('sha256', $destination);
        $warnings = [];
        $locale = setlocale(LC_MESSAGES, 0);
        setlocale(LC_MESSAGES, 'C');
        set_error_handler(static function (int $severity, string $message, string $file, int $line) use (&$warnings): bool {
            if ($severity === E_WARNING && str_contains($message, 'ZipArchive::close()')) {
                $warnings[] = $message;
                return true;
            }
            throw new \ErrorException($message, 0, $severity, $file, $line);
        });
        try {
            try {
                $backup->pack(['sab_temporary_backup_probe' => $this->directory.'/source.dump'], [], $destination);
                self::fail('A package larger than the private tmpfs must not be published.');
            } catch (\RuntimeException $error) {
                self::assertSame('No se pudo cerrar el ZIP; compruebe el espacio disponible.', $error->getMessage());
            }
        } finally {
            restore_error_handler();
            if ($locale !== false) setlocale(LC_MESSAGES, $locale);
        }
        self::assertStringContainsString('No space left on device', implode('\n', $warnings), 'Require ENOSPC, not merely any close failure.');
        self::assertSame($previous, hash_file('sha256', $destination));
        self::assertSame(['previous.zip'], array_values(array_diff(scandir($this->limitedDirectory), ['.', '..'])));
        self::assertSame(['sab_temporary_backup_probe'], $backup->verify($destination)['databases']);
    }

    public function test_unprivileged_child_cannot_publish_in_a_read_only_temporary_directory(): void
    {
        $restricted = $this->directory.'/restricted';
        self::assertTrue(mkdir($restricted, 0555));
        $destination = $restricted.'/denied.zip';
        $probe = new Process([PHP_BINARY, dirname(__DIR__).'/Support/backup_filesystem_probe.php',
            $this->directory.'/source.dump', $destination]);
        $probe->setTimeout(30);
        $probe->mustRun();
        $result = json_decode($probe->getOutput(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(65534, $result['uid']);
        self::assertTrue($result['permission_denied']);
        self::assertContains($result['package_failure_stage'], ['open', 'close']);
        self::assertStringContainsString('Permission denied', implode("\n", $result['warnings']));
        self::assertTrue($result['source_readable']);
        self::assertTrue($result['directory_searchable']);
        self::assertFalse($result['directory_writable']);
        self::assertFalse($result['published']);
        self::assertSame([], array_values(array_diff(scandir($restricted), ['.', '..'])));
    }
}
