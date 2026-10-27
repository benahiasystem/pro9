<?php

namespace App\Services\System;

use enshrined\svgSanitize\Sanitizer;
use Illuminate\Validation\ValidationException;

class LoginSvgSanitizer
{
    public function sanitize(string $contents): string
    {
        $sanitizer = new Sanitizer();
        $sanitizer->removeRemoteReferences(true);
        $clean = $sanitizer->sanitize($contents);

        if (!is_string($clean) || trim($clean) === '') {
            throw ValidationException::withMessages([
                'image' => 'El SVG no es válido o no puede procesarse de forma segura.',
            ]);
        }

        return $clean;
    }
}
