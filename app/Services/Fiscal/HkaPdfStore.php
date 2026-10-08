<?php

namespace App\Services\Fiscal;

use App\Models\Tenant\Document;
use Illuminate\Support\Facades\Storage;

/** Private, per-operation copies of PDFs obtained from HKA. */
final class HkaPdfStore
{
    public function filename(Document $document): string
    {
        $name = \App\CoreFacturalo\Requests\Inputs\Functions::filename(
            (object) ['number' => $document->issuer['number'] ?? ''], '01',
            $document->series, $document->number, $document->establishment_id
        );
        if (!$name || preg_match('/[\\\\\/\x00-\x1f]/', $name) || strlen($name) > 200) {
            throw new \RuntimeException('Invalid fiscal filename');
        }
        return $name;
    }

    public function path(Document $document, string $format): string
    {
        $operation = optional($document->emission)->operation_key;
        if (!in_array($format, ['a4', 'a5'], true)
            || !is_string($operation) || !preg_match('/^[a-f0-9-]{36}$/i', $operation)) {
            throw new \RuntimeException('Invalid PDF operation');
        }
        return 'pdf/hka/'.$operation.'/'.$format.'/'.$this->filename($document).'.pdf';
    }

    public function read(Document $document, string $format): ?string
    {
        $disk = Storage::disk('tenant');
        $path = $this->path($document, $format);
        if (!$disk->exists($path)) return null;
        try {
            $bytes = $disk->get($path);
            HkaPdf::validatePdf($bytes);
            if ($format === 'a5') {
                $reader = new \setasign\Fpdi\Fpdi();
                $pages = $reader->setSourceFile(\setasign\Fpdi\PdfParser\StreamReader::createByString($bytes));
                for ($page = 1; $page <= $pages; $page++) {
                    $size = $reader->getTemplateSize($reader->importPage($page));
                    if (abs($size['width'] - 210) > .02 || abs($size['height'] - 148) > .02) return null;
                }
            }
            return $bytes;
        } catch (\Throwable $exception) {
            return null;
        }
    }

    public function write(Document $document, string $format, string $bytes): void
    {
        HkaPdf::validatePdf($bytes);
        $disk = Storage::disk('tenant');
        $path = $this->path($document, $format);
        $disk->makeDirectory(dirname($path));
        $destination = $disk->path($path);
        $temporary = tempnam(dirname($destination), '.hka-');
        if ($temporary === false) throw new \RuntimeException('PDF storage unavailable');
        try {
            if (file_put_contents($temporary, $bytes, LOCK_EX) !== strlen($bytes)
                || !rename($temporary, $destination)) throw new \RuntimeException('PDF storage failed');
        } finally {
            if (is_file($temporary)) unlink($temporary);
        }
    }
}
