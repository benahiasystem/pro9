<?php

return [

    'user' => env('GIT_USER'),

    'token' => env('GIT_TOKEN') ? trim((string) env('GIT_TOKEN'), " \t\n\r\0\x0B\"'") : null,

    'project_tags_url' => (function() {
        $url = env('GIT_PROJECT_TAGS_URL');
        if (!$url) {
            return null;
        }
        if (preg_match('#(/api/v4/projects/)(.+?)(/repository/tags)#i', $url, $matches)) {
            $prefix = $matches[1];
            $projectPath = $matches[2];
            $suffix = $matches[3];
            if (str_contains($projectPath, '/')) {
                $decoded = rawurldecode($projectPath);
                $encodedPath = str_replace('/', '%2F', $decoded);
                $base = explode('/api/v4/projects/', $url)[0];
                return $base . $prefix . $encodedPath . $suffix;
            }
        }
        return $url;
    })(),

    /** Opcional: solo si el remoto git no coincide con lo derivado de GIT_PROJECT_TAGS_URL. */
    'remote_url' => env('GIT_REMOTE_URL'),

    'filesystem_user' => env('GIT_FILESYSTEM_USER', 'www-data'),

    'auto_fix_permissions' => filter_var(env('GIT_AUTO_FIX_PERMISSIONS', true), FILTER_VALIDATE_BOOLEAN),

    /**
     * Qué refs puede elegir el auto-update (sin variables extra de .env).
     *
     * protected = ramas protegidas en GitLab (default).
     * tags      = tags de GitLab. El selector lista GET {project}/repository/tags
     *             y el pull hace fetch + checkout del tag.
     *
     * No uses un solo tag llamado "prod" que se mueve en cada release: Git no
     * trata bien los tags reescritos y el desplegable solo mostraría un ítem.
     * Cuando versionen entregas, nómbralas inmutables: v9.2.0, v9.2.1, ...
     */
    'update_ref_source' => 'protected',

    /**
     * Rutas rastreadas que suelen ensuciar el working tree y bloquear git pull.
     * Se restauran con `git checkout --` (no es un reset del repositorio).
     */
    'safe_restore_paths' => [
        'storage/app/public/skins',
        'public/mozo',
        'public/vendeya',
    ],

    /**
     * Builds compilados: además del checkout, se elimina lo no trackeado.
     * No incluir skins para no borrar CSS custom sin commitear.
     */
    'safe_clean_paths' => [
        'public/mozo',
        'public/vendeya',
    ],

];
