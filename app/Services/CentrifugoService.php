<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CentrifugoService
{
    public function publish(string $channel, array $data): void
    {
        try {
            $response = Http::withHeaders(['X-API-Key' => config('centrifugo.api_key')])
                ->acceptJson()
                ->timeout(2)
                ->post(rtrim(config('centrifugo.url'), '/') . '/api/publish', compact('channel', 'data'));
            $body = $response->json();
            if (!$response->successful() || !is_array($body) || !array_key_exists('result', $body) || isset($body['error'])) {
                Log::warning('Centrifugo publish rejected', [
                    'channel' => $channel,
                    'status' => $response->status(),
                    'code' => $response->json('error.code'),
                ]);
            }
        } catch (\Exception $e) {
            Log::warning('Centrifugo publish failed', [
                'channel' => $channel,
                'exception' => get_class($e),
            ]);
        }
    }
}
