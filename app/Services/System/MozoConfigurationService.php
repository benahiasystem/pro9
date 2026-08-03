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
            $stored = $this->defaults();
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
