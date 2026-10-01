<?php

namespace App\Services;

use App\Models\Tenant\Company;
use App\Models\Tenant\Establishment;

final class SeriesAdministration
{
    public static function authorize(?int $establishmentId = null): void
    {
        abort_unless(auth()->check() && auth()->user()->type === 'admin', 403);
        if ($establishmentId !== null) Establishment::without(['country', 'department', 'province', 'district'])->findOrFail($establishmentId);
    }

    /** Same lock order as document creation; serialize config and allocation. */
    public static function transaction(callable $action)
    {
        self::authorize();
        return (new Company())->getConnection()->transaction(function () use ($action) {
            Company::query()->lockForUpdate()->firstOrFail();
            return $action();
        }, 3);
    }
}
