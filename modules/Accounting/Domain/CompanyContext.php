<?php

namespace Modules\Accounting\Domain;

use Modules\Accounting\Models\Company;
final class CompanyContext
{
    private ?Company $company = null;
    public function set(Company $company): void
    {
        $this->company = $company;
    }
    public function clear(): void
    {
        $this->company = null;
    }
    public function company(): Company
    {
        if (!$this->company) {
            throw new \LogicException('Falta contexto de empresa.');
        }
        return $this->company;
    }
    public function id(): string
    {
        return $this->company()->id;
    }
    public function organizationId(): string
    {
        return $this->company()->organization_id;
    }
    public function run(Company $company, callable $fn): mixed
    {
        $old = $this->company;
        $this->company = $company;
        try {
            return $fn();
        } finally {
            $this->company = $old;
        }
    }
}
