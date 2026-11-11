<?php

namespace Modules\Accounting\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Crypt, Hash};
use Illuminate\Support\Str;
use Modules\Accounting\Domain\{ApiProblem, Totp};
use Modules\Accounting\Support\TenantDB as DB;
final class SecurityService
{
    public function enabled(int $user): bool
    {
        return DB::table('accounting_user_security')->where('user_id', $user)->whereNotNull('totp_secret')->exists();
    }
    public function verified(Request $r): bool
    {
        $token = (string) $r->header('X-Accounting-Verification');
        return strlen($token) >= 32 && DB::table('accounting_auth_grants')->where('user_id', $r->user()->id)->where('pro9_token_hash', hash('sha256', (string) $r->bearerToken()))->where('verification_hash', hash('sha256', $token))->where('expires_at', '>', now())->exists();
    }
    public function requireVerified(Request $r): void
    {
        if (!$this->enabled($r->user()->id)) {
            throw new ApiProblem('MFA_ENROLLMENT_REQUIRED', 'Activa la verificación en dos pasos en Mi cuenta antes de realizar operaciones definitivas.', 403);
        }
        if (!$this->verified($r)) {
            throw new ApiProblem('MFA_REQUIRED', 'Confirma la verificación en dos pasos para continuar.', 403);
        }
    }
    public function enroll(Request $r, string $password): array
    {
        if (!Hash::check($password, $r->user()->password)) {
            throw new ApiProblem('INVALID_CREDENTIALS', 'La contraseña de PRO9 no es correcta.');
        }
        if ($this->enabled($r->user()->id)) {
            $this->requireVerified($r);
        }
        $secret = (new Totp())->secret();
        $now = now();
        DB::table('accounting_user_security')->updateOrInsert(['user_id' => $r->user()->id], ['pending_secret' => Crypt::encryptString($secret), 'pending_until' => $now->copy()->addMinutes(10), 'created_at' => $now, 'updated_at' => $now]);
        $label = rawurlencode('Contabilidad Benahia:' . $r->user()->email);
        return ['secret' => $secret, 'uri' => 'otpauth://totp/' . $label . '?secret=' . $secret . '&issuer=Contabilidad%20Benahia&algorithm=SHA1&digits=6&period=30'];
    }
    public function confirm(Request $r, string $code): array
    {
        return DB::transaction(function () use ($r, $code) {
            $security = DB::table('accounting_user_security')->where('user_id', $r->user()->id)->lockForUpdate()->first();
            if (!$security?->pending_secret || !$security->pending_until || now()->greaterThan($security->pending_until)) {
                throw new ApiProblem('ENROLLMENT_EXPIRED', 'Vuelve a iniciar la configuración del segundo factor.');
            }
            $secret = Crypt::decryptString($security->pending_secret);
            $counter = (new Totp())->counter($secret, $code, time());
            if ($counter === null) {
                throw new ApiProblem('INVALID_MFA', 'El código no es válido o ya venció.');
            }
            $codes = [];
            for ($i = 0; $i < 8; $i++) {
                $codes[] = bin2hex(random_bytes(8));
            }
            DB::table('accounting_user_security')->where('user_id', $r->user()->id)->update(['totp_secret' => Crypt::encryptString($secret), 'pending_secret' => null, 'pending_until' => null, 'recovery_codes' => json_encode(array_map(fn($c) => hash('sha256', $c), $codes)), 'last_counter' => $counter, 'updated_at' => now()]);
            DB::table('accounting_auth_grants')->where('user_id', $r->user()->id)->delete();
            return ['verification_token' => $this->grant($r), 'recovery_codes' => $codes];
        }, 5);
    }
    public function verify(Request $r, string $code): string
    {
        return DB::transaction(function () use ($r, $code) {
            $security = DB::table('accounting_user_security')->where('user_id', $r->user()->id)->lockForUpdate()->first();
            if (!$security?->totp_secret) {
                throw new ApiProblem('MFA_ENROLLMENT_REQUIRED', 'Activa la verificación en dos pasos en Mi cuenta.', 403);
            }
            $counter = (new Totp())->counter(Crypt::decryptString($security->totp_secret), $code, time(), (int) $security->last_counter);
            if ($counter === null) {
                $recovery = json_decode($security->recovery_codes ?? '[]', true);
                $ix = array_search(hash('sha256', $code), $recovery, true);
                if ($ix === false) {
                    throw new ApiProblem('INVALID_MFA', 'El código no es válido, ya fue utilizado o venció.');
                }
                unset($recovery[$ix]);
                DB::table('accounting_user_security')->where('user_id', $r->user()->id)->update(['recovery_codes' => json_encode(array_values($recovery)), 'updated_at' => now()]);
            } else {
                DB::table('accounting_user_security')->where('user_id', $r->user()->id)->update(['last_counter' => $counter, 'updated_at' => now()]);
            }
            return $this->grant($r);
        }, 5);
    }
    private function grant(Request $r): string
    {
        $token = bin2hex(random_bytes(32));
        DB::table('accounting_auth_grants')->insert(['id' => (string) Str::uuid(), 'user_id' => $r->user()->id, 'pro9_token_hash' => hash('sha256', (string) $r->bearerToken()), 'verification_hash' => hash('sha256', $token), 'expires_at' => now()->addMinutes((int) config('accounting.mfa_minutes', 15)), 'created_at' => now(), 'updated_at' => now()]);
        return $token;
    }
    public function logout(Request $r): void
    {
        DB::table('accounting_auth_grants')->where('user_id', $r->user()->id)->where('verification_hash', hash('sha256', (string) $r->header('X-Accounting-Verification')))->delete();
    }
}
