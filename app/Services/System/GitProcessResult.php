<?php

namespace App\Services\System;

class GitProcessResult
{
    public function __construct(
        public readonly bool $successful,
        public readonly string $output,
        public readonly string $errorOutput,
    ) {
    }

    public function outputTrimmed(): string
    {
        return trim($this->output);
    }

    public function errorTrimmed(): string
    {
        return trim($this->errorOutput);
    }
}
