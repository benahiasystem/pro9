<?php

namespace Modules\Accounting\Http\Controllers;

use Illuminate\Http\Request;
use Modules\Accounting\Domain\ApiProblem;
use Modules\Accounting\Services\AttachmentService;

final class AttachmentController extends \App\Http\Controllers\Controller
{
    public function index(Request $r, string $company, string $type, string $id, AttachmentService $service): array
    {
        $v = $r->validate(['page' => 'nullable|integer|min:1|max:100000']);
        return $service->index($type, $id, $r->user()->id, $v['page'] ?? 1);
    }

    public function store(Request $r, string $company, string $type, string $id, AttachmentService $service): array
    {
        $v = $r->validate(['file' => 'required|file|max:5120', 'title' => 'required|string|max:160', 'reason' => 'required|string|min:10|max:2000', 'supersedes_id' => 'nullable|uuid']);
        $version = trim((string) $r->header('If-Match'), '"');
        if (!preg_match('/^[1-9][0-9]{0,9}$/D', $version)) { throw new ApiProblem('VERSION_REQUIRED', 'Se requiere la versión consultada del registro.', 428); }
        if (!preg_match('/^[a-zA-Z0-9_.:-]{8,100}$/D', (string) $r->header('Idempotency-Key'))) { throw new ApiProblem('IDEMPOTENCY_KEY_REQUIRED', 'Se requiere una identidad válida para adjuntar el soporte.'); }
        return ['data' => $service->upload($type, $id, $r->file('file'), $v, (int) $version, $r->user()->id, $r->header('Idempotency-Key'))];
    }

    public function download(Request $r, string $company, string $id, AttachmentService $service): mixed
    {
        return $this->file($r, $id, $service, false);
    }

    public function preview(Request $r, string $company, string $id, AttachmentService $service): mixed
    {
        return $this->file($r, $id, $service, true);
    }

    private function file(Request $r, string $id, AttachmentService $service, bool $preview): mixed
    {
        $file = $service->download($id, $r->user()->id, $preview);
        $headers = ['Content-Type' => $file['mime'], 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff', 'Content-Security-Policy' => "sandbox; default-src 'none'", 'X-Accounting-Attachment-SHA256' => $file['sha256']];
        return $preview ? response()->file($file['path'], $headers) : response()->download($file['path'], $file['filename'], $headers);
    }
}
