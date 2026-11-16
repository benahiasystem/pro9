<?php

namespace Modules\Accounting\Http\Controllers;

use Illuminate\Http\Request;
use Modules\Accounting\Domain\ApiProblem;
use Modules\Accounting\Services\{SourceIncidentService, SecurityService};

final class SourceIncidentController extends \App\Http\Controllers\Controller
{
    public function index(Request $r, SourceIncidentService $service): array
    {
        return $service->listing($r->validate(['status' => 'nullable|in:open,resolved,superseded,all', 'document_id' => 'nullable|uuid', 'page' => 'nullable|integer|min:1|max:100000']));
    }
    public function show(Request $r, string $company, string $id, SourceIncidentService $service): array { return ['data' => $service->show($id)]; }
    public function resolve(Request $r, string $company, string $id, SourceIncidentService $service): array
    {
        app(SecurityService::class)->requireVerified($r);
        $input = $r->validate(['action' => 'required|in:acknowledge_metadata,apply_after_reversal,review_received', 'reason' => 'required|string|min:10|max:2000', 'source_version_id' => 'required|uuid', 'document_version' => 'required|integer|min:1']);
        $version = trim((string) $r->header('If-Match'), '"');
        if (!preg_match('/^[1-9][0-9]{0,9}$/D', $version)) { throw new ApiProblem('VERSION_REQUIRED', 'Revisa la versión vigente de la incidencia antes de resolverla.', 428); }
        return ['data' => $service->resolve($id, (int) $version, $input, $r->user()->id, $r->header('Idempotency-Key'))];
    }
}
