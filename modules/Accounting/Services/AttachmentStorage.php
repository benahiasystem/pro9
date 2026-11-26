<?php

namespace Modules\Accounting\Services;

use Modules\Accounting\Domain\ApiProblem;
use Modules\Accounting\Models\Attachment;
use Modules\Accounting\Support\PrivateStorage;

final class AttachmentStorage
{
    public function originalRelative(string $id): string { return 'attachments/' . $id . '/original.bin'; }
    public function previewRelative(string $id): string { return 'attachments/' . $id . '/preview.png'; }

    public function check(Attachment $file, bool $preview = false): array
    {
        $relative = $preview ? $file->preview_path : $file->path;
        $expected = $preview ? $this->previewRelative($file->id) : $this->originalRelative($file->id);
        $sha = $preview ? $file->preview_sha256 : $file->sha256;
        if (!$relative || $relative !== $expected || !$sha) { return ['state' => 'unavailable']; }
        // ########## INICIO MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
        $path = app(PrivateStorage::class)->materialize($relative,20 * 1024 * 1024);
        if ($path === null) { return ['state' => 'missing']; }
        // ######### FIN MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
        $digest = @hash_file('sha256', $path);
        if (!$preview && filesize($path) !== $file->size || filesize($path) > 20 * 1024 * 1024 || !is_string($digest) || !hash_equals($sha, $digest)) { return ['state' => 'changed']; }
        if ($file->scan_status !== 'verified') { return ['state' => 'unverified']; }
        return ['state' => 'verified', 'path' => $path];
    }

    public function require(Attachment $file, bool $preview = false): string
    {
        $check = $this->check($file, $preview);
        if ($check['state'] !== 'verified') { throw new ApiProblem('ATTACHMENT_INTEGRITY', 'El soporte falta, cambió o no tiene verificación completa. Conserva su referencia y restaura una copia comprobada; el documento contable no se modificó.', 409, ['attachment_id' => $file->id, 'integrity' => $check['state']]); }
        return $check['path'];
    }
}
