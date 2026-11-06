<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\{Company, User};
use Hyn\Tenancy\Environment;
use Illuminate\Http\Request;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CompanyRifDocumentController extends Controller
{
    private function directory(): string
    {
        $tenant = app(Environment::class)->tenant();
        $uuid = $tenant->uuid ?? null;
        abort_unless(is_string($uuid) && preg_match('/\A[a-zA-Z0-9_-]+\z/', $uuid), 404);
        return $uuid.'/rif_impresa';
    }

    private function company(int $id): Company
    {
        return Company::withoutEagerLoads()->whereKey($id)->firstOrFail();
    }

    public function show(int $id)
    {
        $document = $this->company($id)->rif_document;
        if (!$document) return ['data' => null];
        $path = $this->documentPath($document);
        return ['data' => $path && Storage::disk('tenancy-default')->exists($path)
            ? ['name' => $document['name']] : null];
    }

    public function store(Request $request, int $id)
    {
        if (!(auth()->user() instanceof User) || auth()->user()->type !== 'admin') throw new AuthorizationException();
        $this->company($id);
        $request->validate(['file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240']);
        $file = $request->file('file');
        $extension = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'][$file->getMimeType()] ?? null;
        if ($extension === null) throw ValidationException::withMessages(['file' => 'Seleccione un PDF, JPG o PNG válido.']);
        $disk = Storage::disk('tenancy-default');
        $directory = $this->directory();
        $filename = Str::random(40).'.'.$extension;
        $path = $directory.'/'.$filename;
        $stream = fopen($file->getRealPath(), 'rb');
        if (!$stream) abort(500, 'No se pudo leer el archivo.');
        try {
            if (!$disk->put($path, $stream, 'private')) throw new \RuntimeException('No se pudo guardar el archivo.');
        } catch (\Throwable $e) {
            $disk->delete($path);
            throw $e;
        } finally {
            fclose($stream);
        }
        $name = basename(str_replace('\\', '/', $file->getClientOriginalName()));
        $name = trim(preg_replace('/[\x00-\x1F\x7F]/u', '', $name) ?? '');
        $document = ['file' => $filename, 'name' => mb_substr($name ?: 'rif.'.$extension, 0, 255)];
        try {
            $old = $this->company($id)->getConnection()->transaction(function () use ($id, $document) {
                $company = Company::withoutEagerLoads()->lockForUpdate()->findOrFail($id);
                $old = $company->rif_document;
                $company->rif_document = $document;
                $company->save();
                return $old;
            });
        } catch (\Throwable $e) {
            $disk->delete($path);
            throw $e;
        }
        $oldPath = $this->documentPath($old);
        if ($oldPath && $oldPath !== $path) $disk->delete($oldPath);
        return ['success' => true, 'data' => ['name' => $document['name']]];
    }

    public function download(int $id)
    {
        $document = $this->company($id)->rif_document;
        $path = $this->documentPath($document);
        $disk = Storage::disk('tenancy-default');
        abort_unless($path && $disk->exists($path), 404);
        return $disk->download($path, $document['name'], ['X-Content-Type-Options' => 'nosniff']);
    }

    private function documentPath($document): ?string
    {
        $filename = $document['file'] ?? '';
        if (!is_string($filename) || !preg_match('/\A[a-zA-Z0-9]{40}\.(pdf|jpg|png)\z/', $filename)) return null;
        return $this->directory().'/'.$filename;
    }
}
