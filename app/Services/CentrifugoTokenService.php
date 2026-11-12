<?php

namespace App\Services;

use App\Models\Tenant\User;
use RuntimeException;

class CentrifugoTokenService
{
    /** The controller supplies the hostname and website resolved by tenant middleware. */
    public function issue(User $user, string $fqdn, int $websiteId): array
    {
        $secret = (string) config('centrifugo.token_hmac_secret_key');
        if (strlen($secret) < 32) {
            throw new RuntimeException('Centrifugo token signing is not configured.');
        }

        $subject = 'website:'.$websiteId.':user:'.$user->getKey();
        $channel = 'restaurant:'.$fqdn;
        $issuedAt = time();
        $expiresAt = $issuedAt + (int) config('centrifugo.token_ttl', 300);
        $claims = ['sub' => $subject, 'iat' => $issuedAt, 'exp' => $expiresAt];

        return [
            'token' => $this->sign($claims, $secret),
            'subscription_token' => $this->sign($claims + ['channel' => $channel], $secret),
            'channel' => $channel,
            'expires_at' => $expiresAt,
        ];
    }

    private function sign(array $claims, string $secret): string
    {
        $header = $this->encode(['alg' => 'HS256', 'typ' => 'JWT']);
        $payload = $this->encode($claims);
        $signature = hash_hmac('sha256', $header.'.'.$payload, $secret, true);

        return $header.'.'.$payload.'.'.$this->base64Url($signature);
    }

    private function encode(array $data): string
    {
        return $this->base64Url(json_encode($data, JSON_THROW_ON_ERROR));
    }

    private function base64Url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
