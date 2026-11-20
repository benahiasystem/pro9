<?php

namespace App\Auth;

use App\Http\Controllers\Tenant\PosController;
use App\Models\Tenant\User;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Routing\Route;

/** Defer the branch's geography while authenticating the POS item catalogue. */
final class TenantUserProvider extends EloquentUserProvider
{
    protected function newModelQuery($model=null)
    {
        $model ??= $this->createModel();
        $query=parent::newModelQuery($model);
        $route=app()->bound('request') ? app('request')->route() : null;
        if ($model instanceof User && $route instanceof Route
            && $route->getActionName()===PosController::class.'@item') {
            // Scalar user/branch identifiers still drive the normal authorization.
            // The relationship remains available for ordinary lazy loading.
            $query->without('establishment');
        }
        return $query;
    }
}
