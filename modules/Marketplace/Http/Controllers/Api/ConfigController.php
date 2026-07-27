<?php

namespace Modules\Marketplace\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class ConfigController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json([
            'enabled' => (bool) config('marketplace.enabled', false),
        ]);
    }
}
