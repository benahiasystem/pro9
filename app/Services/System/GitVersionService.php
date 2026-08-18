<?php

namespace App\Services\System;

use Illuminate\Support\Facades\Http;

class GitVersionService
{
    public function __construct(private GitProcessRunner $git)
    {
    }

    public function resolve(): string
    {
        $fromGit = $this->fromGitDescribe();

        if ($fromGit !== '') {
            return $fromGit;
        }

        return $this->fromGitLabTagsApi() ?? '';
    }

    private function fromGitDescribe(): string
    {
        if (! is_dir(base_path('.git'))) {
            return '';
        }

        $result = $this->git->run(['describe', '--tags']);

        if (! $result->successful) {
            return '';
        }

        return $result->outputTrimmed();
    }

    private function fromGitLabTagsApi(): ?string
    {
        $token = config('git.token');
        $tagsUrl = config('git.project_tags_url');

        if (! $token || ! $tagsUrl) {
            return null;
        }

        $response = Http::withHeaders([
            'PRIVATE-TOKEN' => $token,
        ])->get($tagsUrl, [
            'per_page' => 1,
        ]);

        if (! $response->successful()) {
            return null;
        }

        $tags = $response->json();

        if (! is_array($tags) || count($tags) === 0) {
            return null;
        }

        $tag = $tags[0]['name'] ?? null;

        if ($tag === null) {
            return null;
        }

        return $this->enrichTagWithCommit($tag) ?? $tag;
    }

    /**
     * Cuando git describe no corre (p. ej. PHP-FPM como www-data), arma una cadena
     * tipo git describe usando HEAD local (.git) y la API compare de GitLab.
     */
    private function enrichTagWithCommit(string $tag): ?string
    {
        $token = config('git.token');
        $tagsUrl = config('git.project_tags_url');

        if (! $token || ! $tagsUrl) {
            return null;
        }

        $projectUrl = preg_replace('#/repository/tags.*$#', '', $tagsUrl);
        $headers = ['PRIVATE-TOKEN' => $token];

        $headSha = $this->readHeadCommitSha();
        $shortId = $headSha ? substr($headSha, 0, 8) : null;

        if ($headSha === null) {
            $response = Http::withHeaders($headers)->get($projectUrl.'/repository/commits', [
                'per_page' => 1,
            ]);

            if (! $response->successful()) {
                return null;
            }

            $commits = $response->json();

            if (! is_array($commits) || count($commits) === 0) {
                return null;
            }

            $headSha = $commits[0]['id'] ?? null;
            $shortId = $commits[0]['short_id'] ?? ($headSha ? substr($headSha, 0, 8) : null);
        }

        if (! $headSha || ! $shortId) {
            return null;
        }

        $compare = Http::withHeaders($headers)->get($projectUrl.'/repository/compare', [
            'from' => $tag,
            'to' => $headSha,
        ]);

        if ($compare->successful()) {
            $data = $compare->json();
            $count = $data['commits_count'] ?? null;

            if ($count === null && is_array($data['commits'] ?? null)) {
                $count = count($data['commits']);
            }

            if (is_numeric($count) && (int) $count > 0) {
                return $tag.'-'.(int) $count.'-g'.$shortId;
            }
        }

        return $tag.'-g'.$shortId;
    }

    private function readHeadCommitSha(): ?string
    {
        $headFile = base_path('.git/HEAD');

        if (! is_readable($headFile)) {
            return null;
        }

        $head = trim((string) file_get_contents($headFile));

        if (str_starts_with($head, 'ref: ')) {
            $refFile = base_path('.git/'.trim(substr($head, 5)));

            if (! is_readable($refFile)) {
                return null;
            }

            return trim((string) file_get_contents($refFile));
        }

        return $head !== '' ? $head : null;
    }
}
