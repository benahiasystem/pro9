<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CentrifugoService
{
    public function publish(string $channel, array $data): void
    {
        try {
            Http::withHeaders(['X-API-Key' => config('centrifugo.api_key')])
                ->timeout(2)
                ->post(config('centrifugo.url') . '/api/publish', compact('channel', 'data'));
        } catch (\Exception $e) {
            Log::warning('Centrifugo publish failed', [
                'channel' => $channel,
                'error'   => $e->getMessage(),
            ]);
        }
    }
}
