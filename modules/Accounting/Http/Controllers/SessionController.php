<?php

namespace Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Accounting\Services\SecurityService;
final class SessionController extends Controller
{
    public function __construct(private SecurityService $security)
    {
    }
    public function me(Request $r): array
    {
        $u = $r->user();
        return ['user' => ['id' => $u->id, 'name' => $u->name, 'email' => $u->email], 'mfa_required' => app(\Modules\Accounting\Domain\Permissions::class)->requiresMfa($u), 'mfa_session_verified' => $this->security->verified($r, false), 'mfa_enabled' => $this->security->enabled($u->id), 'mfa_verified' => $this->security->verified($r), 'server_time' => now()->toIso8601String(), 'demo' => (bool) config('accounting.demo')];
    }
    public function logout(Request $r): array
    {
        $this->security->logout($r);
        return ['success' => true];
    }
    public function enroll(Request $r): array
    {
        $v = $r->validate(['password' => 'required|string|max:1024']);
        return $this->security->enroll($r, $v['password']);
    }
    public function confirm(Request $r): array
    {
        $v = $r->validate(['code' => 'required|string|max:40']);
        return [...$this->me($r), ...$this->security->confirm($r, $v['code']), 'mfa_enabled' => true, 'mfa_verified' => true, 'mfa_session_verified' => true];
    }
    public function verify(Request $r): array
    {
        $v = $r->validate(['code' => 'required|string|max:40']);
        $token = $this->security->verify($r, $v['code']);
        return [...$this->me($r), 'verification_token' => $token, 'mfa_verified' => true, 'mfa_session_verified' => true];
    }
}
