<?php

namespace App\Services\System;

use App\Models\System\Configuration;

class VendeyaConfigurationService
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
        $stored = $configuration?->vendeya_configuration;

        if (!is_array($stored) || empty($stored)) {
            $stored = $this->defaults();
        }

        return array_replace($this->defaults(), $this->onlyKnownValues($stored));
    }

    public function update(array $values): array
    {
        $configuration = Configuration::query()->firstOrFail();
        $branding = array_replace($this->get(), $this->onlyKnownValues($values));

        $configuration->vendeya_configuration = $branding;
        $configuration->save();

        return $branding;
    }

    private function onlyKnownValues(array $values): array
    {
        $known = array_merge(self::BRANDING_KEYS, self::LOGO_KEYS);

        return array_filter(
            array_intersect_key($values, array_flip($known)),
            static fn ($value) => $value !== null
        );
    }

    private function defaults(): array
    {
        return [
            'brandName' => 'Vendeya.pe',
            'Primary' => '#ff7d00',
            'Secondary' => '#d5e8e8',
            'Accent' => '#ffffff',
            'Background' => '#eef5f5',
            'White' => '#ffffff',
            'Text' => '#004850',
            'lightText' => '#a2a5b9',
            'darkPrimary' => '#121c22',
            'darkSecondary' => '#1c2a32',
            'darkAccent' => '#253945',
            'darkBackground' => '#1b262c',
            'darkLightText' => '#a9a9b2',
            'useSystemLogo' => true,
            'logoVersion' => null,
        ];
    }
}
