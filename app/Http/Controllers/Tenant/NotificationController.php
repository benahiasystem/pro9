<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Helpers\HeaderNotifications;
use Illuminate\Support\Facades\Log;

class NotificationController extends Controller
{
    public function header()
    {
        try {
            return response()->json((new HeaderNotifications())->getAll());
        } catch (\Throwable $exception) {
            Log::error('Failed to load header notifications: ' . $exception->getMessage(), [
                'exception' => $exception,
            ]);

            return response()->json([
                'total_count' => 0,
                'notifications' => [],
            ]);
        }
    }
}
