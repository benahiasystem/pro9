<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Services\System\GitProcessResult;
use App\Services\System\GitProcessRunner;
use App\Services\System\GitRemoteService;
use App\Services\System\GitVersionService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Process\Process;

class UpdateController extends Controller
{
    public function __construct(
        private GitVersionService $gitVersion,
        private GitProcessRunner $git,
        private GitRemoteService $gitRemote,
    ) {
    }

    public function index()
    {
        return view('system.update.index');
    }

    public function version()
    {
        return response()->json($this->gitVersion->resolve());
    }

    private function phpProcessUser(): string
    {
        if (function_exists('posix_geteuid') && function_exists('posix_getpwuid')) {
            $info = posix_getpwuid(posix_geteuid());

            return $info['name'] ?? get_current_user();
        }

        return get_current_user();
    }

    /**
     * Git pull necesita crear .git/index.lock; el usuario de PHP-FPM debe poder escribir en .git
     */
    private function checkGitDirectoryWritable(): array
    {
        $gitPath = base_path('.git');
        $phpUser = $this->phpProcessUser();

        if (! is_dir($gitPath)) {
            return [
                'writable' => false,
                'message' => 'No existe .git en el proyecto.',
                'php_user' => $phpUser,
                'setup_command' => null,
            ];
        }

        $writable = is_writable($gitPath);
        $testFile = $gitPath.'/.write-test-'.uniqid('', true);
        if ($writable) {
            $writable = @file_put_contents($testFile, '1') !== false;
            if ($writable) {
                @unlink($testFile);
            }
        }

        if ($writable) {
            return [
                'writable' => true,
                'message' => "El usuario PHP ({$phpUser}) puede escribir en .git",
                'php_user' => $phpUser,
                'setup_command' => null,
            ];
        }

        if (config('git.auto_fix_permissions', true)) {
            $this->attemptGitDirectoryPermissionFix($gitPath);
            $writable = is_writable($gitPath);
            $testFile = $gitPath.'/.write-test-'.uniqid('', true);
            if ($writable) {
                $writable = @file_put_contents($testFile, '1') !== false;
                if ($writable) {
                    @unlink($testFile);
                }
            }
            if ($writable) {
                return [
                    'writable' => true,
                    'message' => 'Permisos de .git listos para el usuario PHP ('.$phpUser.')',
                    'php_user' => $phpUser,
                    'setup_command' => null,
                ];
            }
        }

        $owner = fileowner($gitPath);
        $group = filegroup($gitPath);
        $ownerName = function_exists('posix_getpwuid') ? (posix_getpwuid($owner)['name'] ?? $owner) : $owner;
        $groupName = function_exists('posix_getgrgid') ? (posix_getgrgid($group)['name'] ?? $group) : $group;
        $setupCommand = 'chown -R '.$phpUser.':'.$phpUser.' '.base_path('.git');

        return [
            'writable' => false,
            'message' => "Sin escritura en .git (dueño {$ownerName}:{$groupName}, PHP es {$phpUser}).",
            'php_user' => $phpUser,
            'setup_command' => $setupCommand,
        ];
    }

    /**
     * chown solo si PHP es root; chmod se intenta siempre.
     */
    private function attemptGitDirectoryPermissionFix(string $gitPath): void
    {
        if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
            $target = config('git.filesystem_user', 'www-data');
            $chown = new Process(['chown', '-R', $target.':'.$target, $gitPath], base_path());
            $chown->setTimeout(120);
            $chown->run();
        }

        $chmod = new Process(['chmod', '-R', 'ug+rwX', $gitPath], base_path());
        $chmod->setTimeout(120);
        $chmod->run();
    }

    public function preCheck()
    {
        $token = $this->gitRemote->token();
        $tagsUrl = config('git.project_tags_url');

        $hasGitDir = is_dir(base_path('.git'));
        $gitWriteCheck = $this->checkGitDirectoryWritable();

        $tokenValid = false;
        $tokenMessage = 'Token no configurado (GIT_TOKEN en .env). Si usas config:cache, vuelve a generarlo tras cambiar .env.';

        if (! $tagsUrl) {
            $tokenMessage = 'GIT_PROJECT_TAGS_URL no configurado en .env';
        } elseif ($token) {
            try {
                $response = Http::withHeaders([
                    'PRIVATE-TOKEN' => $token,
                ])->get($tagsUrl, ['per_page' => 1]);

                if ($response->successful()) {
                    $tokenValid = true;
                    $tokenMessage = 'Token válido y activo con GitLab';
                } else {
                    $tokenMessage = 'Token inválido o expirado (código '.$response->status().')';
                }
            } catch (\Exception $e) {
                $tokenMessage = 'Error al conectar con GitLab: '.$e->getMessage();
            }
        }

        $this->git->run(['version']);

        $remoteCheck = $this->gitRemote->verifyRemoteAccess();
        $gitConnected = $remoteCheck['connected'];
        $gitMessage = $remoteCheck['message'];

        $versionResolved = $this->gitVersion->resolve();
        $versionMessage = $versionResolved !== ''
            ? "Versión detectada: {$versionResolved}"
            : 'No se pudo resolver la versión (revisa permisos de git para el usuario PHP-FPM)';

        $ready = $hasGitDir && $gitWriteCheck['writable'] && $tokenValid && $gitConnected;

        return response()->json([
            'ready' => $ready,
            'token_valid' => $tokenValid,
            'token_message' => $tokenMessage,
            'git_connected' => $gitConnected,
            'git_message' => $gitMessage,
            'has_git_dir' => $hasGitDir,
            'git_dir_message' => $hasGitDir
                ? 'Directorio .git presente en la aplicación'
                : 'No se encontró .git. El pull no funcionará.',
            'git_writable' => $gitWriteCheck['writable'],
            'git_writable_message' => $gitWriteCheck['message'],
            'setup_command' => $gitWriteCheck['setup_command'] ?? null,
            'version_resolved' => $versionResolved,
            'version_message' => $versionMessage,
        ]);
    }

    public function branches()
    {
        $currentBranch = $this->gitRemote->currentBranchName();
        $branches = $this->gitRemote->listRemoteBranchNames();

        if ($currentBranch !== '' && ! in_array($currentBranch, $branches, true)) {
            $branches[] = $currentBranch;
            sort($branches, SORT_NATURAL | SORT_FLAG_CASE);
        }

        if ($branches === []) {
            $branches = [$currentBranch !== '' ? $currentBranch : 'main'];
        }

        return response()->json([
            'current' => $currentBranch,
            'list' => array_values($branches),
        ]);
    }

    public function branch()
    {
        $result = $this->git->runOrFail(['rev-parse', '--abbrev-ref', 'HEAD']);

        return response()->json($result->outputTrimmed());
    }

    public function pull($branch)
    {
        $this->allowLongRunningProcess();

        try {
            $remoteRepo = $this->gitRemote->remoteForGitCommands();
            $branch = trim($branch);

            if (! $this->gitRemote->isAllowedUpdateRef($branch)) {
                $kind = $this->gitRemote->updateRefSource() === 'tags' ? 'tag' : 'rama protegida';

                return response()->json([
                    'message' => "\"{$branch}\" no es un {$kind} permitido para auto-update.",
                    'dirty_files' => [],
                ], 422);
            }

            $lockPath = base_path('composer.lock');
            $lockBefore = is_file($lockPath) ? hash_file('sha256', $lockPath) : null;

            $restoredPaths = $this->restoreSafePaths();
            $cleanedPaths = $this->cleanSafePaths();
            $headBefore = $this->git->run(['rev-parse', 'HEAD'])->outputTrimmed();

            if ($this->gitRemote->updateRefSource() === 'tags') {
                $pullResult = $this->checkoutTag($remoteRepo, $branch);
            } else {
                $this->git->run([
                    'fetch',
                    $remoteRepo,
                    '+refs/heads/'.$branch.':refs/remotes/origin/'.$branch,
                ], 300);

                $checkoutResult = $this->git->run(['checkout', $branch]);

                if (! $checkoutResult->successful) {
                    $this->git->runOrFail(['checkout', '-B', $branch, 'origin/'.$branch]);
                }

                $pullResult = $this->git->run(['pull', $remoteRepo, $branch], 300);
            }

            if (! $pullResult->successful) {
                return $this->pullFailureResponse($pullResult);
            }

            $this->git->run([
                'fetch',
                $remoteRepo,
                '+refs/heads/*:refs/remotes/origin/*',
                '--prune',
            ], 300);

            $headAfter = $this->git->run(['rev-parse', 'HEAD'])->outputTrimmed();
            $output = $pullResult->output;
            $alreadyUpToDate = $headBefore !== '' && $headBefore === $headAfter;

            $lockAfter = is_file($lockPath) ? hash_file('sha256', $lockPath) : null;

            return response()->json([
                'output' => $output,
                'already_up_to_date' => $alreadyUpToDate,
                'composer_lock_changed' => $lockBefore !== $lockAfter,
                'restored_paths' => $restoredPaths,
                'cleaned_paths' => $cleanedPaths,
            ]);
        } catch (\Throwable $e) {
            return $this->pullFailureResponse(null, $this->git->redact($e->getMessage()));
        }
    }

    private function checkoutTag(string $remoteRepo, string $tag): GitProcessResult
    {
        $fetch = $this->git->run([
            'fetch',
            $remoteRepo,
            'refs/tags/'.$tag.':refs/tags/'.$tag,
            '--force',
        ], 300);

        if (! $fetch->successful) {
            return $fetch;
        }

        return $this->git->run(['checkout', '--detach', $tag]);
    }

    /**
     * @return list<string>
     */
    private function restoreSafePaths(): array
    {
        $paths = $this->configuredGitPaths('safe_restore_paths');

        if ($paths === []) {
            return [];
        }

        $this->git->run(array_merge(['checkout', '--'], $paths));

        return $paths;
    }

    /**
     * @return list<string>
     */
    private function cleanSafePaths(): array
    {
        $paths = $this->configuredGitPaths('safe_clean_paths');

        if ($paths === []) {
            return [];
        }

        $this->git->run(array_merge(['clean', '-fd', '--'], $paths));

        return $paths;
    }

    /**
     * @return list<string>
     */
    private function configuredGitPaths(string $key): array
    {
        $paths = config('git.'.$key, []);

        if (! is_array($paths)) {
            return [];
        }

        return array_values(array_filter($paths, function ($path) {
            return is_string($path) && $path !== '';
        }));
    }

    private function pullFailureResponse(?GitProcessResult $result = null, ?string $fallbackMessage = null)
    {
        $status = $this->git->run(['status', '--porcelain']);
        $dirtyFiles = array_values(array_filter(
            explode("\n", $status->outputTrimmed()),
            fn ($line) => $line !== ''
        ));

        $message = $fallbackMessage
            ?: ($result ? ($result->errorTrimmed() ?: $result->outputTrimmed()) : '')
            ?: 'Error al ejecutar git pull';

        if ($dirtyFiles !== []) {
            $message .= "\n\nArchivos locales que impiden la actualización:\n".implode("\n", $dirtyFiles);
        }

        return response()->json([
            'message' => $message,
            'dirty_files' => $dirtyFiles,
        ], 422);
    }

    private function allowLongRunningProcess(): void
    {
        ignore_user_abort(true);
        set_time_limit(0);
    }

    public function artisanMigrate()
    {
        $this->allowLongRunningProcess();
        Artisan::call('migrate', ['--force' => true]);

        return response()->json(Artisan::output());
    }

    public function artisanTenancyMigrate()
    {
        $this->allowLongRunningProcess();
        Artisan::call('tenancy:migrate', ['--force' => true]);

        return response()->json(Artisan::output());
    }

    public function artisanConfigCache()
    {
        $this->allowLongRunningProcess();
        Artisan::call('config:cache');

        return response()->json(Artisan::output() ?: 'Configuration cached successfully.');
    }

    public function artisanCacheClear()
    {
        $this->allowLongRunningProcess();
        Artisan::call('cache:clear');

        return response()->json(Artisan::output() ?: 'Application cache cleared.');
    }

    public function artisanClear()
    {
        $this->allowLongRunningProcess();
        Artisan::call('optimize:clear');

        return response()->json(Artisan::output());
    }

    public function composerInstall()
    {
        $this->allowLongRunningProcess();
        $process = new Process(['composer', 'install', '--no-dev', '-d', base_path()], base_path());
        $process->setTimeout(600);
        $process->run();
        $output = $process->getOutput();
        $errorOutput = $process->getErrorOutput();

        $totalOutput = "=== COMPOSER INSTALL ===\n".$output."\n".$errorOutput;

        try {
            $dirs = [
                base_path('bootstrap/cache'),
                storage_path('framework/cache'),
                storage_path('framework/sessions'),
                storage_path('framework/testing'),
                storage_path('framework/views'),
            ];
            $totalOutput .= "\n\n=== CREACIÓN DE DIRECTORIOS ===";
            foreach ($dirs as $dir) {
                if (! File::exists($dir)) {
                    File::makeDirectory($dir, 0775, true, true);
                    $totalOutput .= "\n[Creado] ".basename($dir);
                } else {
                    $totalOutput .= "\n[Existe] ".basename($dir);
                }
            }
        } catch (\Exception $e) {
            $totalOutput .= "\n[Error] Creando directorios: ".$e->getMessage();
        }

        try {
            $totalOutput .= "\n\n=== ASIGNACIÓN DE PERMISOS (chmod 775) ===";
            $chmodStorage = new Process(['chmod', '-R', '775', storage_path(), base_path('bootstrap/cache')], base_path());
            $chmodStorage->run();
            $totalOutput .= "\nPermisos 775 aplicados recursivamente a storage/ y bootstrap/cache/";
        } catch (\Exception $e) {
            $totalOutput .= "\n[Error] Aplicando chmod 775: ".$e->getMessage();
        }

        if (config('app.env') !== 'local') {
            try {
                $totalOutput .= "\n\n=== CAMBIO DE PROPIETARIO (chown www-data) ===";
                $chownProcess = new Process(['chown', '-R', 'www-data:www-data', storage_path(), base_path('bootstrap/cache')], base_path());
                $chownProcess->run();
                $totalOutput .= "\nPropietario cambiado a www-data:www-data para storage/ y bootstrap/cache/";
            } catch (\Exception $e) {
                $totalOutput .= "\n[Error] Aplicando chown: ".$e->getMessage();
            }
        } else {
            $totalOutput .= "\n\n=== CAMBIO DE PROPIETARIO (chown www-data) ===\nEntorno local detectado. Se omite el cambio de propietario.";
        }

        try {
            $totalOutput .= "\n\n=== COMPOSER DUMP-AUTOLOAD ===";
            $dumpProcess = new Process(['composer', 'dump-autoload', '-d', base_path()], base_path());
            $dumpProcess->run();
            $totalOutput .= "\n".$dumpProcess->getOutput();
        } catch (\Exception $e) {
            $totalOutput .= "\n[Error] En composer dump-autoload: ".$e->getMessage();
        }

        try {
            $totalOutput .= "\n\n=== CONFIGURAR PDF WRITER (mpdf 777) ===";
            $chmodMpdf = new Process(['chmod', '-R', '777', base_path('vendor/mpdf/mpdf')], base_path());
            $chmodMpdf->run();
            $totalOutput .= "\nPermisos 777 aplicados a vendor/mpdf/mpdf";
        } catch (\Exception $e) {
            $totalOutput .= "\n[Error] Aplicando chmod 777 a mpdf: ".$e->getMessage();
        }

        return response()->json($totalOutput);
    }

    public function keygen()
    {
        // deprecated
    }

    public function changelog()
    {
        try {
            $result = $this->git->run([
                'log',
                '-n',
                '40',
                '--date=short',
                '--pretty=format:* **%ad** [%h] - %s',
            ]);

            if ($result->successful && $result->outputTrimmed() !== '') {
                $markdown = $result->outputTrimmed();
            } elseif (File::exists(base_path('CHANGELOG.md'))) {
                $markdown = File::get(base_path('CHANGELOG.md'));
            } else {
                throw new \RuntimeException('No se pudo leer el historial de git.');
            }

            if (class_exists(\League\CommonMark\GithubFlavoredMarkdownConverter::class)) {
                $converter = new \League\CommonMark\GithubFlavoredMarkdownConverter([
                    'html_input' => 'strip',
                    'allow_unsafe_links' => false,
                ]);

                return $converter->convert($markdown)->getContent();
            }

            $items = '';
            foreach (preg_split('/\r\n|\r|\n/', $markdown) as $line) {
                $line = trim($line, " \t*-");
                if ($line !== '') {
                    $items .= '<li>'.e($line).'</li>';
                }
            }

            return '<ul>'.$items.'</ul>';
        } catch (\Throwable $e) {
            return response('<p class="text-muted">No se pudo cargar el changelog.</p>', 200)
                ->header('Content-Type', 'text/html; charset=UTF-8');
        }
    }
}
