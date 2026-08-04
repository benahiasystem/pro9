<?php

namespace App\Helpers;

use App\Models\Tenant\User;
use Hyn\Tenancy\Contracts\CurrentHostname;
use Illuminate\Support\Str;

class MozoAccessHelper
{
    public function generateLink(User $user): string
    {
        if (!$user->api_token) {
            $user->api_token = Str::random(50);
        }

        $user->secret_login_time = now();
        $user->save();

        $hash = $this->createHash($user->fresh());
        $protocol = config('tenant.force_https') ? 'https' : 'http';
        $fqdn = app(CurrentHostname::class)->fqdn;

        return "{$protocol}://{$fqdn}/mozo/entrar/{$hash}";
    }

    public function createHash(User $user): string
    {
        return sha1($this->createKeyString($user));
    }

    public function createKeyString(User $user): string
    {
        return implode('|', [
            'mozo_access',
            'fqdn' => app(CurrentHostname::class)->fqdn,
            'secret_login_time' => $user->secret_login_time,
            'user_id' => $user->id,
            'user_email' => $user->email,
            'user_api_token' => $user->api_token,
        ]);
    }

    public function findUserByHash(string $hash): ?User
    {
        $users = User::query()
            ->whereNotNull('restaurant_role_id')
            ->get();

        foreach ($users as $user) {
            if (hash_equals($this->createHash($user), $hash)) {
                return $user;
            }
        }

        return null;
    }
}
