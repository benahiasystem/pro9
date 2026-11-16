<?php

namespace Modules\Accounting\Http\Controllers;

use Illuminate\Http\Request;
use Modules\Accounting\Services\SourceHistoryService;

final class SourceHistoryController extends \App\Http\Controllers\Controller
{
    public function index(Request $request, string $company, string $id, SourceHistoryService $history): array
    {
        $v = $request->validate(['page' => 'nullable|integer|min:1|max:100000']);
        return $history->listing($id, $v['page'] ?? 1);
    }
    public function show(Request $request, string $company, string $id, string $version, SourceHistoryService $history): array
    {
        return ['data' => $history->show($id, $version)];
    }
    public function revision(Request $request, string $company, string $id, int $revision, SourceHistoryService $history): array
    {
        return ['data' => $history->revision($id, $revision)];
    }
}
