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

    public function get(): array
    {
        $configuration = Configuration::query()->first();
        $stored = $configuration?->mozo_configuration;

        if (!is_array($stored) || empty($stored)) {
            $stored = $this->buildFallback();
        }

        return array_replace($this->defaults(), $this->onlyBrandingValues($stored));
    }

    public function update(array $values): array
    {
        $configuration = Configuration::query()->firstOrFail();
        $branding = array_replace($this->get(), $this->onlyBrandingValues($values));

        $configuration->mozo_configuration = $branding;
        $configuration->save();

        return $branding;
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
            ? array_replace($this->defaults(), $this->onlyBrandingValues($decoded))
            : $this->defaults();
    }

    private function onlyBrandingValues(array $values): array
    {
        return array_intersect_key($values, array_flip(self::BRANDING_KEYS));
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
        ];
    }
}
