<?php

namespace Tests\Unit;

use App\Models\Tenant\Company;
use Modules\Company\Models\Company as ModuleCompany;
use PHPUnit\Framework\TestCase;

class CompanyMtcRemovalTest extends TestCase
{
    public function test_legacy_mtc_is_not_fillable_or_serialized_but_signature_remains_available(): void
    {
        foreach ([Company::class, ModuleCompany::class] as $modelClass) {
            $company = new $modelClass();
            $company->setRawAttributes(['mtc_code' => 'LEGACY', 'img_firm' => 'firma.png']);
            $company->fill(['mtc_code' => 'CHANGED', 'img_firm' => 'nueva.png']);

            self::assertSame('LEGACY', $company->getAttribute('mtc_code'));
            self::assertArrayNotHasKey('mtc_code', $company->toArray());
            self::assertSame('nueva.png', $company->toArray()['img_firm']);
        }
    }
}
