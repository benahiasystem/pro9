<?php

namespace Tests\Feature;

use Tests\TestCase;

class LoginLogoVisibilityTest extends TestCase
{
    /** @dataProvider logoSettings */
    public function test_logo_visibility_follows_setting_for_both_login_modes(bool $global, bool $showLogo): void
    {
        $data = [
            'useLoginGlobal' => $global,
            'login' => (object) [
                'image' => '/images/fondo-sab.svg',
                'logo' => '/images/global-logo.png',
                'position_logo' => 'top-left',
                'show_logo_in_form' => $showLogo,
            ],
            'company' => (object) ['logo' => 'company-logo.png'],
        ];

        $side = view('tenant.auth.partials.side_left', $data)->render();
        $form = view('tenant.auth.partials.form_logo', $data)->render();

        $this->assertStringContainsString('/images/fondo-sab.svg', $side);
        $this->assertSame($showLogo, str_contains($side, 'class="auth__logo top-left"'));
        $this->assertSame($showLogo, str_contains($form, 'class="auth__logo-form"'));
    }

    public static function logoSettings(): array
    {
        return [[false, false], [false, true], [true, false], [true, true]];
    }

    /** @dataProvider backgroundSettings */
    public function test_background_visibility_is_independent_of_company_logo(bool $global, ?bool $showBackground): void
    {
        $login = (object) [
            'image' => '/images/fondo-sab.svg',
            'logo' => '/images/global-logo.png',
            'position_logo' => 'top-left',
            'show_logo_in_form' => true,
        ];
        if ($showBackground !== null) {
            $login->show_background_image = $showBackground;
        }

        $side = view('tenant.auth.partials.side_left', [
            'useLoginGlobal' => $global,
            'login' => $login,
            'company' => (object) ['logo' => 'company-logo.png'],
        ])->render();

        $this->assertSame($showBackground ?? true, str_contains($side, '/images/fondo-sab.svg'));
        $this->assertStringContainsString('class="auth__logo top-left"', $side);
    }

    public static function backgroundSettings(): array
    {
        return [[false, null], [false, true], [false, false], [true, null], [true, true], [true, false]];
    }
}
