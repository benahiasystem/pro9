<?php

namespace App\Services\System;

use Illuminate\Support\Facades\Http;

class GitRemoteService
{
    public function __construct(
        private GitProcessRunner $git,
    ) {
    }

    public function hasToken(): bool
    {
        return $this->token() !== null && $this->token() !== '';
    }

    public function token(): ?string
    {
        $token = config('git.token');

        if ($token === null || $token === '') {
            return null;
        }

        return trim((string) $token, " \t\n\r\0\x0B\"'");
    }

    public function user(): string
    {
        $user = config('git.user');

        return ($user !== null && $user !== '') ? trim((string) $user) : 'oauth2';
    }

    /**
     * URL HTTPS del remoto sin credenciales embebidas.
     */
    public function plainRemoteUrl(): string
    {
        $result = $this->git->run(['remote', 'get-url', 'origin']);

        if ($result->successful && $result->outputTrimmed() !== '') {
            return $this->stripCredentialsFromUrl($result->outputTrimmed());
        }

        $override = config('git.remote_url');

        if ($override) {
            return (string) $override;
        }

        return $this->deriveRemoteUrlFromTagsApiUrl() ?? '';
    }

    /**
     * URL para git ls-remote / fetch / pull (nunca devuelve el alias "origin").
     */
    public function remoteForGitCommands(): string
    {
        $authUrl = $this->authenticatedRemoteUrl();

        if ($authUrl !== null) {
            return $authUrl;
        }

        return $this->plainRemoteUrl();
    }

    public function authenticatedRemoteUrl(): ?string
    {
        $token = $this->token();

        if ($token === null) {
            return null;
        }

        $remoteUrl = $this->plainRemoteUrl();

        if ($remoteUrl === '' || ! str_starts_with($remoteUrl, 'https://')) {
            return null;
        }

        $urlParts = parse_url($remoteUrl);

        if (! isset($urlParts['host'], $urlParts['path'])) {
            return null;
        }

        $scheme = $urlParts['scheme'] ?? 'https';
        $port = isset($urlParts['port']) ? ':'.$urlParts['port'] : '';

        return sprintf(
            '%s://%s:%s@%s%s%s',
            $scheme,
            rawurlencode($this->user()),
            rawurlencode($token),
            $urlParts['host'],
            $port,
            $urlParts['path']
        );
    }

    /**
     * Verifica acceso al repo: API GitLab (token) y opcionalmente git ls-remote.
     */
    public function verifyRemoteAccess(): array
    {
        $token = $this->token();

        if ($token === null) {
            return [
                'connected' => false,
                'message' => 'GIT_TOKEN no está cargado (revisa .env y ejecuta php artisan config:clear).',
                'method' => 'none',
            ];
        }

        if (! config('git.project_tags_url')) {
            return [
                'connected' => false,
                'message' => 'Configura GIT_PROJECT_TAGS_URL en .env (URL API de tags del proyecto en GitLab).',
                'method' => 'none',
            ];
        }

        $projectUrl = $this->projectApiBaseUrl();

        if ($projectUrl !== null) {
            try {
                $response = Http::withHeaders([
                    'PRIVATE-TOKEN' => $token,
                ])->timeout(15)->get($projectUrl);

                if ($response->successful()) {
                    return [
                        'connected' => true,
                        'message' => 'Acceso al repositorio verificado con GitLab API',
                        'method' => 'api',
                    ];
                }
            } catch (\Throwable $e) {
                // continuar con git ls-remote
            }
        }

        $remote = $this->remoteForGitCommands();

        if ($remote === '') {
            return [
                'connected' => false,
                'message' => 'No se pudo obtener la URL del repositorio. Revisa GIT_PROJECT_TAGS_URL o define GIT_REMOTE_URL en .env.',
                'method' => 'none',
            ];
        }

        $result = $this->git->run(['ls-remote', '--heads', $remote]);

        if ($result->successful) {
            return [
                'connected' => true,
                'message' => 'Conexión Git exitosa (ls-remote)',
                'method' => 'git',
            ];
        }

        $error = $result->errorTrimmed() ?: $result->outputTrimmed();

        return [
            'connected' => false,
            'message' => 'Error en conexión Git: '.$error,
            'method' => 'git',
        ];
    }

    public function projectApiBaseUrl(): ?string
    {
        $tagsUrl = config('git.project_tags_url');

        if (! $tagsUrl) {
            return null;
        }

        return preg_replace('#/repository/tags.*$#', '', $tagsUrl);
    }

    /**
     * Rama actual leyendo .git/HEAD (fiable con www-data) y git como respaldo.
     */
    public function currentBranchName(): string
    {
        $fromFile = $this->currentBranchFromHeadFile();

        if ($fromFile !== null) {
            return $fromFile;
        }

        $result = $this->git->run(['rev-parse', '--abbrev-ref', 'HEAD']);

        if ($result->successful) {
            $branch = $result->outputTrimmed();

            if ($branch !== '' && $branch !== 'HEAD') {
                return $branch;
            }
        }

        return 'main';
    }

    public function currentBranchFromHeadFile(): ?string
    {
        $headFile = base_path('.git/HEAD');

        if (! is_readable($headFile)) {
            return null;
        }

        $head = trim((string) file_get_contents($headFile));

        if (str_starts_with($head, 'ref: refs/heads/')) {
            $branch = trim(substr($head, strlen('ref: refs/heads/')));

            return $branch !== '' ? $branch : null;
        }

        return null;
    }

    public function updateRefSource(): string
    {
        $source = config('git.update_ref_source', 'protected');

        return $source === 'tags' ? 'tags' : 'protected';
    }

    /**
     * Refs que el auto-update puede ofrecer.
     *
     * @return list<string>
     */
    public function listRemoteBranchNames(): array
    {
        if ($this->updateRefSource() === 'tags') {
            return $this->listReleaseTags();
        }

        $branches = $this->listProtectedBranchesFromGitLabApi();
        $branches = array_values(array_unique($branches));
        sort($branches, SORT_NATURAL | SORT_FLAG_CASE);

        return $branches;
    }

    public function isAllowedUpdateRef(string $ref): bool
    {
        $ref = trim($ref);

        if ($ref === '' || ! $this->isSafeRefName($ref)) {
            return false;
        }

        if ($this->updateRefSource() === 'tags') {
            return in_array($ref, $this->listReleaseTags(), true);
        }

        if ($ref === $this->currentBranchName()) {
            return true;
        }

        return $this->isProtectedBranch($ref);
    }

    public function isSafeRefName(string $name): bool
    {
        if ($name === '' || str_contains($name, '..')) {
            return false;
        }

        return (bool) preg_match('/^[A-Za-z0-9._\/-]+$/', $name);
    }

    public function isProtectedBranch(string $branch): bool
    {
        $token = $this->token();
        $base = $this->projectApiBaseUrl();

        if ($token === null || $base === null || $branch === '') {
            return false;
        }

        try {
            $response = Http::withHeaders([
                'PRIVATE-TOKEN' => $token,
            ])->timeout(15)->get($base.'/repository/branches/'.rawurlencode($branch));
        } catch (\Throwable $e) {
            return false;
        }

        if (! $response->successful()) {
            return false;
        }

        $data = $response->json();

        return is_array($data) && ! empty($data['protected']);
    }

    /**
     * @return list<string>
     */
    private function listProtectedBranchesFromGitLabApi(): array
    {
        $token = $this->token();
        $base = $this->projectApiBaseUrl();

        if ($token === null || $base === null) {
            return [];
        }

        $names = [];
        $page = 1;

        do {
            try {
                $response = Http::withHeaders([
                    'PRIVATE-TOKEN' => $token,
                ])->timeout(20)->get($base.'/repository/branches', [
                    'per_page' => 100,
                    'page' => $page,
                ]);
            } catch (\Throwable $e) {
                break;
            }

            if (! $response->successful()) {
                break;
            }

            $data = $response->json();

            if (! is_array($data) || $data === []) {
                break;
            }

            foreach ($data as $branch) {
                if (! empty($branch['name']) && ! empty($branch['protected'])) {
                    $names[] = (string) $branch['name'];
                }
            }

            $page++;
        } while (count($data) === 100);

        return $names;
    }

    /**
     * Tags de release vía API GitLab. Activo si update_ref_source = tags.
     * Nombres inmutables (v9.2.0); un tag único "prod" que se mueve no escala.
     *
     * @return list<string>
     */
    private function listReleaseTags(): array
    {
        $token = $this->token();
        $tagsUrl = config('git.project_tags_url');

        if ($token === null || ! $tagsUrl) {
            return [];
        }

        $names = [];
        $page = 1;

        do {
            try {
                $response = Http::withHeaders([
                    'PRIVATE-TOKEN' => $token,
                ])->timeout(20)->get($tagsUrl, [
                    'per_page' => 100,
                    'page' => $page,
                ]);
            } catch (\Throwable $e) {
                break;
            }

            if (! $response->successful()) {
                break;
            }

            $data = $response->json();

            if (! is_array($data) || $data === []) {
                break;
            }

            foreach ($data as $tag) {
                if (! empty($tag['name'])) {
                    $names[] = (string) $tag['name'];
                }
            }

            $page++;
        } while (count($data) === 100);

        sort($names, SORT_NATURAL | SORT_FLAG_CASE);

        return $names;
    }

    /**
     * @return list<string>
     */
    private function listBranchesFromLsRemote(): array
    {
        $remote = $this->remoteForGitCommands();

        if ($remote === '') {
            return [];
        }

        $result = $this->git->run(['ls-remote', '--heads', $remote]);

        if (! $result->successful) {
            return [];
        }

        $names = [];

        foreach (explode("\n", $result->output) as $line) {
            $line = trim($line);

            if ($line !== '' && preg_match('#refs/heads/(.+)$#', $line, $matches)) {
                $names[] = $matches[1];
            }
        }

        return $names;
    }

    /**
     * @return list<string>
     */
    private function listBranchesFromLocalTracking(): array
    {
        $result = $this->git->run(['branch', '-r']);

        if (! $result->successful) {
            return [];
        }

        $names = [];

        foreach (explode("\n", $result->output) as $line) {
            $line = trim($line);

            if ($line === '' || str_contains($line, '->')) {
                continue;
            }

            $line = (string) preg_replace('#^(remotes/)?origin/#', '', $line);

            if ($line !== '' && $line !== 'HEAD') {
                $names[] = $line;
            }
        }

        return $names;
    }

    /**
     * De .../api/v4/projects/org%2Frepo%2Fpro8/repository/tags → https://host/org/repo/pro8.git
     */
    public function deriveRemoteUrlFromTagsApiUrl(): ?string
    {
        $tagsUrl = config('git.project_tags_url');

        if (! $tagsUrl || ! preg_match(
            '#^(https?://[^/]+)/api/v4/projects/([^/]+)/repository/tags#',
            $tagsUrl,
            $matches
        )) {
            return null;
        }

        $base = rtrim($matches[1], '/');
        $projectPath = urldecode($matches[2]);

        return $base.'/'.$projectPath.'.git';
    }

    private function stripCredentialsFromUrl(string $url): string
    {
        return (string) preg_replace('#^https://[^@/]+@#', 'https://', $url);
    }
}
