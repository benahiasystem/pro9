<?php

namespace Modules\Accounting\Http\Controllers;

use Illuminate\Http\Request;
use Modules\Accounting\Domain\ApiProblem;
use Modules\Accounting\Services\ConflictService;

final class ConflictController extends \App\Http\Controllers\Controller
{
    private function version(Request $request): int
    {
        $value = trim((string) $request->header('If-Match'), '"');
        if (!preg_match('/^[1-9][0-9]{0,9}$/D', $value)) {
            throw new ApiProblem('VERSION_REQUIRED', 'Se requiere la versión de SAB que revisaste para guardar esta decisión.', 428);
        }
        return (int) $value;
    }

    private function input(Request $request): array
    {
        $input = $request->validate(['base_version' => 'nullable|integer|min:1', 'decision' => 'required|in:apply_local,keep_server', 'reason' => 'required|string|min:10|max:2000', 'local' => 'required|array']);
        $input['base_version'] = isset($input['base_version']) ? (int) $input['base_version'] : null;
        return $input;
    }

    public function showEntry(Request $request, string $company, string $id): array
    {
        return ['data' => app(ConflictService::class)->currentEntry($id)];
    }

    public function showDocument(Request $request, string $company, string $id): array
    {
        return ['data' => app(ConflictService::class)->currentDocument($id)];
    }

    public function entry(Request $request, string $company, string $id): array
    {
        return ['data' => app(ConflictService::class)->entry($request->user()->id, $id, $this->version($request), $this->input($request), $request->header('Idempotency-Key'))];
    }

    public function document(Request $request, string $company, string $id): array
    {
        return ['data' => app(ConflictService::class)->document($request->user()->id, $id, $this->version($request), $this->input($request), $request->header('Idempotency-Key'))];
    }
}
