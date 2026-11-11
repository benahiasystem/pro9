<?php

namespace Modules\Accounting\Models;

use Modules\Accounting\Domain\CompanyContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
abstract class OwnedModel extends Model
{
    use HasUuids;
    use \Hyn\Tenancy\Traits\UsesTenantConnection;
    public function __construct(array $attributes = [])
    {
        // Set the physical name once; Eloquent may later set a query alias.
        $this->table ??= 'accounting_' . \Illuminate\Support\Str::snake(\Illuminate\Support\Str::pluralStudly(class_basename($this)));
        parent::__construct($attributes);
    }
    protected $guarded = ['organization_id', 'company_id'];
    protected static function booted(): void
    {
        static::addGlobalScope('company', function ($q) {
            $c = app(CompanyContext::class);
            $q->where($q->qualifyColumn('organization_id'), $c->organizationId())->where($q->qualifyColumn('company_id'), $c->id());
        });
        static::created(function ($model) {
            $model->refresh();
        });
        static::creating(function ($model) {
            $c = app(CompanyContext::class);
            $model->organization_id = $c->organizationId();
            $model->company_id = $c->id();
        });
        static::updating(function ($model) {
            $c = app(CompanyContext::class);
            if ($model->company_id !== $c->id() || $model->organization_id !== $c->organizationId()) {
                throw new \LogicException('Contexto de empresa incompatible.');
            }
            if ($model->isDirty('company_id') || $model->isDirty('organization_id')) {
                throw new \LogicException('No se puede mover un recurso entre empresas.');
            }
        });
    }
}
