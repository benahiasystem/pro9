<?php

namespace Modules\Accounting\Support;

use Modules\Accounting\Domain\ApiProblem;

final class AttachmentFormat
{
    public const TYPES = ['pdf' => ['application/pdf'], 'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'], 'png' => ['image/png'], 'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'], 'csv' => ['text/plain', 'text/csv', 'application/csv'], 'txt' => ['text/plain'], 'xml' => ['text/plain', 'text/xml', 'application/xml']];

    private function xml(string $bytes, ?callable $node = null): void
    {
        $previous = libxml_use_internal_errors(true);
        $reader = new \XMLReader();
        try {
            libxml_clear_errors();
            if (!$reader->XML($bytes, null, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING)) { throw new ApiProblem('ATTACHMENT_FORMAT_XML', 'El archivo XML no se pudo comprobar.'); }
            $reader->setParserProperty(\XMLReader::LOADDTD, false); $reader->setParserProperty(\XMLReader::SUBST_ENTITIES, false);
            $count = 0;
            while ($reader->read()) {
                if (++$count > 1000000 || $reader->depth > 64 || in_array($reader->nodeType, [\XMLReader::DOC_TYPE, \XMLReader::ENTITY, \XMLReader::ENTITY_REF], true)) { throw new ApiProblem('ATTACHMENT_FORMAT_XML', 'El XML contiene entidades, referencias externas o una estructura fuera de los límites permitidos.'); }
                if ($node) { $node($reader); }
            }
            if (libxml_get_errors()) { throw new ApiProblem('ATTACHMENT_FORMAT_XML', 'La estructura XML no se pudo comprobar completamente.'); }
        } finally { $reader->close(); libxml_clear_errors(); libxml_use_internal_errors($previous); }
    }

    private function xlsx(string $path): void
    {
        $zip = new \ZipArchive();
        if ($zip->open($path, \ZipArchive::RDONLY) !== true) { throw new ApiProblem('ATTACHMENT_FORMAT_XLSX', 'El XLSX no es un libro válido.'); }
        try {
            if ($zip->numFiles > 2000 || $zip->locateName('[Content_Types].xml') === false || $zip->locateName('xl/workbook.xml') === false) { throw new ApiProblem('ATTACHMENT_FORMAT_XLSX', 'El archivo no contiene un libro XLSX dentro de los límites permitidos.'); }
            $expanded = 0;
            for ($i = 0; $i < $zip->numFiles; ++$i) {
                $entry = $zip->statIndex($i); $name = $entry['name']; $lower = strtolower($name); $expanded += $entry['size'];
                if ($expanded > 40 * 1024 * 1024 || ($entry['encryption_method'] ?? 0) !== 0 || str_contains($name, '\\') || str_starts_with($name, '/') || in_array('..', explode('/', $name), true) || str_contains($lower, 'vbaproject') || str_contains($lower, 'xl/embeddings/') || str_contains($lower, 'xl/externallinks/')) { throw new ApiProblem('ATTACHMENT_FORMAT_XLSX', 'El libro contiene macros, objetos, vínculos de datos externos, cifrado o contenido fuera de los límites permitidos.'); }
                if (str_ends_with($lower, '.xml') || str_ends_with($lower, '.rels')) {
                    $bytes = $zip->getFromIndex($i);
                    if ($bytes === false || strlen($bytes) !== $entry['size']) { throw new ApiProblem('ATTACHMENT_FORMAT_XLSX', 'No se pudo comprobar una parte completa del libro.'); }
                    $this->xml($bytes, function ($reader) {
                        if ($reader->nodeType === \XMLReader::ELEMENT && $reader->localName === 'Relationship' && $reader->getAttribute('TargetMode') === 'External') {
                            $type = $reader->getAttribute('Type') ?? ''; $url = $reader->getAttribute('Target') ?? '';
                            if (!str_ends_with($type, '/hyperlink') || !preg_match('#^https?://#i', $url)) { throw new ApiProblem('ATTACHMENT_FORMAT_XLSX', 'El libro contiene una relación externa que no se admite como soporte.'); }
                        }
                        if ($reader->nodeType === \XMLReader::ELEMENT && str_contains(strtolower((string) $reader->getAttribute('ContentType')), 'macroenabled')) { throw new ApiProblem('ATTACHMENT_FORMAT_XLSX', 'No se admiten libros con macros.'); }
                    });
                }
            }
        } finally { $zip->close(); }
    }

    /** Original bytes remain intact; only the optional raster preview is recoded. */
    public function inspect(string $path, string $extension, string $directory): array
    {
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        if (!isset(self::TYPES[$extension]) || !in_array($mime, self::TYPES[$extension], true)) { throw new ApiProblem('ATTACHMENT_TYPE_MISMATCH', 'La extensión y el tipo del archivo no corresponden a un formato admitido.'); }
        $preview = null;
        if ($extension === 'pdf') {
            $file = fopen($path, 'rb');
            try { $start = fread($file, 1024); fseek($file, max(0, filesize($path) - 4096)); $end = stream_get_contents($file); }
            finally { fclose($file); }
            if (!preg_match('/%PDF-[12]\.[0-9]/', $start) || !str_contains($end, '%%EOF')) { throw new ApiProblem('ATTACHMENT_FORMAT_PDF', 'No se pudo comprobar la estructura básica del PDF.'); }
        } elseif ($extension === 'xlsx') { $this->xlsx($path); $mime = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'; }
        elseif ($extension === 'xml') { $this->xml(file_get_contents($path)); $mime = 'application/xml'; }
        elseif (in_array($extension, ['png', 'jpg', 'jpeg'], true)) {
            $size = @getimagesize($path);
            if (!$size || !$size[0] || !$size[1] || $size[0] > 10000 || $size[1] > 10000 || $size[0] * $size[1] > 20000000) { throw new ApiProblem('ATTACHMENT_IMAGE_SIZE', 'La imagen supera 20 megapíxeles o sus dimensiones no se pudieron comprobar.'); }
            $image = $extension === 'png' ? @imagecreatefrompng($path) : @imagecreatefromjpeg($path);
            if (!$image) { throw new ApiProblem('ATTACHMENT_FORMAT_IMAGE', 'La imagen no se pudo decodificar completamente.'); }
            $factor = min(1, 1600 / max($size[0], $size[1])); $width = max(1, (int) round($size[0] * $factor)); $height = max(1, (int) round($size[1] * $factor)); $thumb = imagecreatetruecolor($width, $height);
            try {
                imagealphablending($thumb, false); imagesavealpha($thumb, true); imagecopyresampled($thumb, $image, 0, 0, 0, 0, $width, $height, $size[0], $size[1]);
                $preview = $directory . '/preview.png';
                if (!imagepng($thumb, $preview, 6)) { throw new \RuntimeException('No se pudo preparar la vista segura de la imagen.'); }
            } finally { imagedestroy($thumb); imagedestroy($image); }
        } elseif ($extension === 'csv') { $mime = 'text/csv'; }
        return ['mime' => $mime, 'preview' => $preview];
    }
}
