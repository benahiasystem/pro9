<?php

namespace App\Services\System;

use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

class GitProcessRunner
{
    private static bool $safeDirectoryInitialized = false;

    public function __construct(
        private readonly ?string $workingDirectory = null,
        private readonly int $timeout = 120,
    ) {
    }

    public function run(array $gitArgs, ?int $timeout = null): GitProcessResult
    {
        $cwd = $this->resolveWorkingDirectory();
        $this->ensureSafeDirectoryForPhpUser($cwd);
        $command = $this->buildCommand($gitArgs, $cwd);

        $process = $this->createProcess($command, $cwd);
        $process->setTimeout($timeout ?? $this->timeout);
        $process->run();

        return new GitProcessResult(
            $process->isSuccessful(),
            $this->redact($process->getOutput()),
            $this->redact($process->getErrorOutput()),
        );
    }

    public function runOrFail(array $gitArgs, ?int $timeout = null): GitProcessResult
    {
        $result = $this->run($gitArgs, $timeout);

        if (! $result->successful) {
            $message = $result->errorTrimmed() ?: $result->outputTrimmed();

            if ($message === '') {
                $message = 'Error al ejecutar git '.$this->redact(implode(' ', $gitArgs));
            }

            throw new \RuntimeException($message);
        }

        return $result;
    }

    public function redact(string $text): string
    {
        $redacted = preg_replace('#https?://[^/@:\s]+:[^/@\s]+@#i', 'https://***@', $text) ?? $text;
        $token = config('git.token');

        if (is_string($token) && $token !== '') {
            $redacted = str_replace($token, '***', $redacted);
        }

        return $redacted;
    }

    private function resolveWorkingDirectory(): string
    {
        $path = $this->workingDirectory ?? base_path();
        $real = realpath($path);

        return $real !== false ? $real : $path;
    }

    /**
     * Persiste safe.directory para el usuario PHP (www-data), no solo root del contenedor.
     */
    private function ensureSafeDirectoryForPhpUser(string $cwd): void
    {
        if (self::$safeDirectoryInitialized) {
            return;
        }

        $gitHome = $this->gitHomeDirectory();
        File::ensureDirectoryExists($gitHome);

        foreach ([$cwd, '*'] as $directory) {
            $process = $this->createProcess(
                [
                    'git',
                    '-c', 'safe.directory=*',
                    '-c', 'safe.directory='.$cwd,
                    'config',
                    '--global',
                    '--add',
                    'safe.directory',
                    $directory,
                ],
                $cwd
            );
            $process->setTimeout(30);
            $process->run();
        }

        self::$safeDirectoryInitialized = true;
    }

    private function gitHomeDirectory(): string
    {
        return storage_path('app/git-home');
    }

    private function createProcess(array $command, string $cwd): Process
    {
        $process = new Process($command, $cwd);
        $process->setEnv($this->gitProcessEnvOverrides($cwd));

        return $process;
    }

    /**
     * @return array<string, string>
     */
    private function gitProcessEnvOverrides(string $cwd): array
    {
        return [
            'HOME' => $this->gitHomeDirectory(),
            'GIT_CONFIG_COUNT' => '2',
            'GIT_CONFIG_KEY_0' => 'safe.directory',
            'GIT_CONFIG_VALUE_0' => '*',
            'GIT_CONFIG_KEY_1' => 'safe.directory',
            'GIT_CONFIG_VALUE_1' => $cwd,
        ];
    }

    /**
     * @return list<string>
     */
    private function buildCommand(array $gitArgs, string $cwd): array
    {
        return array_merge(
            [
                'git',
                '-c', 'safe.directory=*',
                '-c', 'safe.directory='.$cwd,
            ],
            $gitArgs
        );
    }
}
