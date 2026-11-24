<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class ConsoleRuntimeUserTest extends TestCase
{
    public function test_artisan_rejects_an_unknown_configured_user_before_booting(): void
    {
        if (!function_exists('posix_geteuid')) {
            self::markTestSkipped('La prueba requiere POSIX.');
        }
        $process = new Process([PHP_BINARY, dirname(__DIR__, 2).'/artisan', '--version'], null, ['SAB_ARTISAN_USER' => 'sab-user-that-does-not-exist']);
        $process->run();
        self::assertFalse($process->isSuccessful());
        self::assertStringContainsString('No existe el usuario configurado para Artisan', $process->getErrorOutput());
    }

    public function test_wrong_unprivileged_user_is_rejected(): void
    {
        if (!function_exists('posix_geteuid') || posix_geteuid() !== 0 || !posix_getpwnam('www-data')) {
            self::markTestSkipped('La prueba de cambio de identidad requiere root y www-data.');
        }
        $code = <<<'PHP'
require $argv[1].'/vendor/autoload.php';
putenv('SAB_ARTISAN_USER=www-data');
\App\Support\ConsoleRuntimeUser::apply();
putenv('SAB_ARTISAN_USER=root');
\App\Support\ConsoleRuntimeUser::apply();
PHP;
        $process = new Process([PHP_BINARY, '-r', $code, dirname(__DIR__, 2)]);
        $process->run();
        self::assertFalse($process->isSuccessful());
        self::assertStringContainsString('Artisan debe ejecutarse como el usuario configurado', $process->getErrorOutput());
    }

    public function test_root_console_creates_private_tenant_files_and_compiled_views_as_runtime_user(): void
    {
        if (!function_exists('posix_geteuid') || posix_geteuid() !== 0 || !posix_getpwnam('www-data')) {
            self::markTestSkipped('La prueba de cambio de identidad requiere root y www-data.');
        }

        $directory = sys_get_temp_dir().'/sab-runtime-user-'.bin2hex(random_bytes(8));
        mkdir($directory, 0700);
        chown($directory, 'www-data');

        try {
            $code = <<<'PHP'
require $argv[1].'/vendor/autoload.php';
\App\Support\ConsoleRuntimeUser::apply();
$root = $argv[2];
$adapter = new \League\Flysystem\Local\LocalFilesystemAdapter($root.'/tenants/new-client');
$disk = new \League\Flysystem\Filesystem($adapter);
$disk->write('pdf/document.pdf', 'fixture');
mkdir($root.'/views', 0700);
file_put_contents($root.'/template.blade.php', 'Hello {{ $name }}');
$compiler = new \Illuminate\View\Compilers\BladeCompiler(new \Illuminate\Filesystem\Filesystem(), $root.'/views');
$compiler->compile($root.'/template.blade.php');
echo json_encode([posix_geteuid(), fileowner($root.'/tenants/new-client'), fileperms($root.'/tenants/new-client') & 0777, fileowner($compiler->getCompiledPath($root.'/template.blade.php'))]);
PHP;
            $process = new Process([PHP_BINARY, '-r', $code, dirname(__DIR__, 2), $directory], null, ['SAB_ARTISAN_USER' => 'www-data']);
            $process->mustRun();
            $uid = posix_getpwnam('www-data')['uid'];
            self::assertSame([$uid, $uid, 0700, $uid], json_decode($process->getOutput(), true));
        } finally {
            $cleanup = new Process(['rm', '-rf', $directory]);
            $cleanup->mustRun();
        }
    }
}
