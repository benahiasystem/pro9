<?php

namespace App\Services\System;

use App\Models\System\Configuration;
use Illuminate\Support\Facades\Http;

class GitRemoteService
{
    private ?Configuration $configuration = null;

    private bool $configurationLoaded = false;

    public function __construct(
        private GitProcessRunner $git,
    ) {
    }

    /**
     * Valor guardado desde Sistema > Configuraciones > Integraciones.
     * Devuelve null si no está configurado o si la tabla aún no existe.
     */
    private function fromDatabase(string $column): ?string
    {
        if (! $this->configurationLoaded) {
            $this->configurationLoaded = true;

            try {
                $this->configuration = Configuration::first();
            } catch (\Throwable $e) {
                $this->configuration = null;
            }
        }

        if ($this->configuration === null) {
            return null;
        }

        $value = trim((string) ($this->configuration->{$column} ?? ''));

        return $value !== '' ? $value : null;
    }

    /**
     * Proveedor del repositorio: define endpoints y cabecera de autenticación.
     * Se toma de la configuración; si no está definido se deduce del host y,
     * como último recurso, se asume GitLab (comportamiento histórico del .env).
     */
    public function provider(): string
    {
        $provider = strtolower((string) $this->fromDatabase('git_provider'));

        if ($provider === 'github' || $provider === 'gitlab') {
            return $provider;
        }

        $host = strtolower((string) parse_url($this->plainRemoteUrl(), PHP_URL_HOST));

        if ($host !== '' && str_contains($host, 'github')) {
            return 'github';
        }

        return 'gitlab';
    }

    public function providerLabel(): string
    {
        return $this->provider() === 'github' ? 'GitHub' : 'GitLab';
    }

    /**
     * Cabeceras de autenticación de la API según proveedor.
     *
     * @return array<string, string>
     */
    public function apiHeaders(): array
    {
        $token = $this->token();

        if ($token === null) {
            return [];
        }

        if ($this->provider() === 'github') {
            return [
                'Authorization' => 'Bearer '.$token,
                'Accept' => 'application/vnd.github+json',
                'X-GitHub-Api-Version' => '2022-11-28',
            ];
        }

        return ['PRIVATE-TOKEN' => $token];
    }

    /**
     * URL de la API del proyecto derivada de la URL remota configurada.
     * Solo aplica cuando la URL se guardó desde el panel; si no, manda el .env.
     */
    private function projectApiBaseUrlFromRemote(): ?string
    {
        $remote = $this->fromDatabase('git_remote_url');

        if ($remote === null) {
            return null;
        }

        $parts = parse_url($remote);

        if (! isset($parts['host'], $parts['path'])) {
            return null;
        }

        $path = trim((string) preg_replace('/\.git$/i', '', $parts['path']), '/');

        if ($path === '') {
            return null;
        }

        $scheme = $parts['scheme'] ?? 'https';
        $host = $parts['host'];
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';

        if ($this->provider() === 'github') {
            // github.com usa api.github.com; GitHub Enterprise expone /api/v3 en el propio host.
            if (in_array(strtolower($host), ['github.com', 'www.github.com'], true)) {
                return 'https://api.github.com/repos/'.$path;
            }

            return $scheme.'://'.$host.$port.'/api/v3/repos/'.$path;
        }

        return $scheme.'://'.$host.$port.'/api/v4/projects/'.rawurlencode($path);
    }

    public function tagsApiUrl(): ?string
    {
        $base = $this->projectApiBaseUrl();

        if ($base === null) {
            return null;
        }

        return $this->provider() === 'github' ? $base.'/tags' : $base.'/repository/tags';
    }

    public function branchesApiUrl(): ?string
    {
        $base = $this->projectApiBaseUrl();

        if ($base === null) {
            return null;
        }

        return $this->provider() === 'github' ? $base.'/branches' : $base.'/repository/branches';
    }

    /**
     * Valida el token contra la API del proveedor (usado por el pre-check del auto-update).
     *
     * @return array{valid: bool, message: string}
     */
    public function verifyToken(): array
    {
        $token = $this->token();

        if ($token === null) {
            return [
                'valid' => false,
                'message' => 'Token no configurado. Defínelo en Configuraciones > Integraciones > Repositorio remoto (o GIT_TOKEN en .env).',
            ];
        }

        $tagsUrl = $this->tagsApiUrl();

        if ($tagsUrl === null) {
            return [
                'valid' => false,
                'message' => 'Falta la URL del repositorio. Defínela en Configuraciones > Integraciones > Repositorio remoto (o GIT_PROJECT_TAGS_URL en .env).',
            ];
        }

        try {
            $response = Http::withHeaders($this->apiHeaders())
                ->timeout(15)
                ->get($tagsUrl, ['per_page' => 1]);

            if ($response->successful()) {
                return [
                    'valid' => true,
                    'message' => 'Token válido y activo con '.$this->providerLabel(),
                ];
            }

            return [
                'valid' => false,
                'message' => 'Token inválido o sin permisos suficientes (código '.$response->status().')',
            ];
        } catch (\Throwable $e) {
            return [
                'valid' => false,
                'message' => 'Error al conectar con '.$this->providerLabel().': '.$e->getMessage(),
            ];
        }
    }

    public function hasToken(): bool
    {
        return $this->token() !== null && $this->token() !== '';
    }

    public function token(): ?string
    {
        $token = $this->fromDatabase('git_token') ?? config('git.token');

        if ($token === null || $token === '') {
            return null;
        }

        return trim((string) $token, " \t\n\r\0\x0B\"'");
    }

    public function user(): string
    {
        $user = $this->fromDatabase('git_user') ?? config('git.user');

        return ($user !== null && $user !== '') ? trim((string) $user) : 'oauth2';
    }

    /**
     * URL HTTPS del remoto sin credenciales embebidas.
     */
    public function plainRemoteUrl(): string
    {
        $configured = $this->fromDatabase('git_remote_url');

        if ($configured !== null) {
            return $this->stripCredentialsFromUrl($configured);
        }

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
                'message' => 'Token no configurado. Defínelo en Configuraciones > Integraciones > Repositorio remoto (o GIT_TOKEN en .env).',
                'method' => 'none',
            ];
        }

        $projectUrl = $this->projectApiBaseUrl();

        if ($projectUrl === null && $this->plainRemoteUrl() === '') {
            return [
                'connected' => false,
                'message' => 'Configura la URL remota en Configuraciones > Integraciones > Repositorio remoto (o GIT_PROJECT_TAGS_URL / GIT_REMOTE_URL en .env).',
                'method' => 'none',
            ];
        }

        if ($projectUrl !== null) {
            try {
                $response = Http::withHeaders($this->apiHeaders())
                    ->timeout(15)
                    ->get($projectUrl);

                if ($response->successful()) {
                    return [
                        'connected' => true,
                        'message' => 'Acceso al repositorio verificado con la API de '.$this->providerLabel(),
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
                'message' => 'No se pudo obtener la URL del repositorio. Revísala en Configuraciones > Integraciones > Repositorio remoto.',
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
        $fromRemote = $this->projectApiBaseUrlFromRemote();

        if ($fromRemote !== null) {
            return $fromRemote;
        }

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

        $branches = $this->listProtectedBranchesFromApi();
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
        $branchesUrl = $this->branchesApiUrl();

        if ($token === null || $branchesUrl === null || $branch === '') {
            return false;
        }

        try {
            $response = Http::withHeaders($this->apiHeaders())
                ->timeout(15)
                ->get($branchesUrl.'/'.rawurlencode($branch));
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
    private function listProtectedBranchesFromApi(): array
    {
        $token = $this->token();
        $branchesUrl = $this->branchesApiUrl();

        if ($token === null || $branchesUrl === null) {
            return [];
        }

        $query = [
            'per_page' => 100,
            'page' => 1,
        ];

        // GitHub filtra en el servidor; GitLab devuelve todas y se filtra por 'protected'.
        if ($this->provider() === 'github') {
            $query['protected'] = 'true';
        }

        $names = [];
        $page = 1;

        do {
            $query['page'] = $page;

            try {
                $response = Http::withHeaders($this->apiHeaders())
                    ->timeout(20)
                    ->get($branchesUrl, $query);
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
        $tagsUrl = $this->tagsApiUrl();

        if ($token === null || $tagsUrl === null) {
            return [];
        }

        $names = [];
        $page = 1;

        do {
            try {
                $response = Http::withHeaders($this->apiHeaders())
                    ->timeout(20)
                    ->get($tagsUrl, [
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
