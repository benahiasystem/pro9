<?php

namespace Modules\Accounting\Http\Controllers;

use Illuminate\Http\Request;
use Modules\Accounting\Services\OperationService;

final class OperationController extends \App\Http\Controllers\Controller
{
    public function show(Request $request, string $company, string $action, string $key): array
    {
        return ['data' => app(OperationService::class)->status($request->user()->id, $action, $key)];
    }

    public function cancel(Request $request, string $company, string $action, string $key): array
    {
        $input = $request->validate(['reason' => 'required|string|min:10|max:2000']);
        return ['data' => app(OperationService::class)->cancel($request->user()->id, $action, $key, $input['reason'])];
    }
}
