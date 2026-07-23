<?php

namespace App\Services\System;

use App\Models\System\Configuration;

class MozoConfigurationService
{
    private const BRANDING_KEYS = [
        'brandName',
        'Primary',
        'Secondary',
        'Accent',
        'Background',
        'White',
        'Text',
        'lightText',
        'darkPrimary',
        'darkSecondary',
        'darkAccent',
        'darkBackground',
        'darkLightText',
    ];

    private const LOGO_KEYS = [
        'useSystemLogo',
        'logoVersion',
    ];

    public function get(): array
    {
        $configuration = Configuration::query()->first();
        $stored = $configuration?->mozo_configuration;

        if (!is_array($stored) || empty($stored)) {
            $stored = $this->buildFallback();
        }

        return array_replace($this->defaults(), $this->onlyKnownValues($stored));
    }

    public function update(array $values): array
    {
        $configuration = Configuration::query()->firstOrFail();
        $branding = array_replace($this->get(), $this->onlyKnownValues($values));

        $configuration->mozo_configuration = $branding;
        $configuration->save();

        return $branding;
    }

    /**
     * Escribe la marca guardada en BD (nombre + colores) sobre el config.json
     * que lee el Mozo, preservando las claves ajenas a la marca (apiUrl, apiSsl,
     * isStoreEnabled, etc.). Idempotente: solo escribe si hubo cambios.
     *
     * Permite que la personalización de marca persista tras actualizar el build.
     *
     * @return bool true si el archivo se actualizó, false si no hubo cambios o no fue posible.
     */
    public function syncConfigFile(): bool
    {
        $path = public_path('mozo/config.json');

        if (!is_file($path) || !is_writable($path)) {
            return false;
        }

        $existing = [];
        $contents = @file_get_contents($path);
        if ($contents !== false) {
            $decoded = json_decode($contents, true);
            if (is_array($decoded)) {
                $existing = $decoded;
            }
        }

        $branding = array_intersect_key($this->get(), array_flip(self::BRANDING_KEYS));
        $merged = array_replace($existing, $branding);

        if ($merged === $existing) {
            return false;
        }

        $json = json_encode($merged, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return file_put_contents($path, $json) !== false;
    }

    private function buildFallback(): array
    {
        $path = public_path('mozo/config.json');

        if (!is_file($path) || !is_readable($path)) {
            return $this->defaults();
        }

        $contents = file_get_contents($path);
        $decoded = $contents === false ? null : json_decode($contents, true);

        return is_array($decoded)
            ? array_replace($this->defaults(), $this->onlyKnownValues($decoded))
            : $this->defaults();
    }

    private function onlyKnownValues(array $values): array
    {
        $known = array_merge(self::BRANDING_KEYS, self::LOGO_KEYS);

        return array_intersect_key($values, array_flip($known));
    }

    private function defaults(): array
    {
        return [
            'brandName' => 'Mozo.pe',
            'Primary' => '#32a56a',
            'Secondary' => '#f58f00',
            'Accent' => '#115733',
            'Background' => '#f4f5f6',
            'White' => '#ffffff',
            'Text' => '#1d3a3a',
            'lightText' => '#a2a5b9',
            'darkPrimary' => '#222225',
            'darkSecondary' => '#27272a',
            'darkAccent' => '#313135',
            'darkBackground' => '#3b3b40',
            'darkLightText' => '#d0d2dc',
            'useSystemLogo' => true,
            'logoVersion' => null,
        ];
    }
}
