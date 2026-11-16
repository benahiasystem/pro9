<?php

namespace Modules\Accounting\Http\Middleware;

use Modules\Accounting\Domain\{ApiProblem, CompanyContext, Permissions};
use Modules\Accounting\Models\Company;
use Closure;
use Illuminate\Http\Request;
final class CompanyAccess
{
    public function handle(Request $request, Closure $next): mixed
    {
        $id = (string) $request->route('company');
        $m = app(Permissions::class)->membership($request->user(), $id);
        $company = Company::where('id', $id)->where('organization_id', $m->organization_id)->where('active', true)->first();
        if (!$company) {
            throw new ApiProblem('COMPANY_NOT_AVAILABLE', 'La empresa no está disponible.', 403);
        }
        $context = app(CompanyContext::class);
        $context->set($company);
        try {
            return $next($request);
        } finally {
            $context->clear();
        }
    }
}
