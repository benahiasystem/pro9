<?php

namespace Modules\Accounting\Http\Controllers;

use Illuminate\Http\Request;
use Modules\Accounting\Services\{AuditReadService, AuditIntegrityService};

final class AuditController extends \App\Http\Controllers\Controller
{
    public function index(Request $r, AuditReadService $service): array
    {
        return $service->index($r->validate(AuditReadService::rules()));
    }

    public function options(AuditReadService $service): array
    {
        return ['data' => $service->options()];
    }

    public function integrity(AuditIntegrityService $service): array
    {
        return ['data' => $service->verify()];
    }

    public function show(Request $r, string $company, string $id, AuditReadService $service): array
    {
        return ['data' => $service->show($id)];
    }
}
