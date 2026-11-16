<?php

namespace Modules\Accounting\Http\Controllers;

use Modules\Accounting\Services\ReportService;
use Illuminate\Http\Request;
final class ReportController extends \App\Http\Controllers\Controller
{
    public function show(Request $r, string $company, string $kind, ReportService $service): array
    {
        $v = $r->validate(\Modules\Accounting\Support\ReportInput::rules());
        return ['data' => $service->build($kind, $v)];
    }
}
