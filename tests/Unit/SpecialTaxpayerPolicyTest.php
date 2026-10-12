<?php
namespace Tests\Unit;

use App\Services\Fiscal\SpecialTaxpayerPolicy as Policy;
use Tests\Support\SpeProfiles;
use Tests\TestCase;

class SpecialTaxpayerPolicyTest extends TestCase
{
    public function test_spe_is_not_an_identity_type_or_a_special_regime(): void
    {
        foreach (['natural','legal','public'] as $nature) {
            $p = SpeProfiles::profile(['nature'=>$nature,'rif'=>($nature==='natural'?'V':($nature==='public'?'G':'J')).'-12345678-9','iva_agent_basis'=>'none','personal_firm'=>false,
                'public_kind'=>$nature==='public'?'national':null]);
            self::assertTrue(Policy::spe(Policy::validate($p),'2026-09-10'));
        }
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        Policy::validate(SpeProfiles::profile(['notification_reference'=>null]));
    }
    public function test_natural_spe_requires_a_personal_firm_to_act_under_general_iva_rule(): void
    {
        $natural = SpeProfiles::profile(['nature'=>'natural','personal_firm'=>false,'iva_agent_basis'=>'none']);
        self::assertFalse(Policy::retention(SpeProfiles::profile(),$natural,'2026-09-10')['eligible']);
        $natural['personal_firm']=true;$natural['iva_agent_basis']='SNAT/2025/000054';
        self::assertTrue(Policy::retention(SpeProfiles::profile(),$natural,'2026-09-10')['eligible']);
    }
    public function test_public_profiles_use_specific_scope_and_general_exclusions(): void
    {
        foreach (Policy::PUBLIC_KINDS as $kind) {
            $p=SpeProfiles::profile(['nature'=>'public','rif'=>'G-12345678-9','public_kind'=>$kind,'iva_agent_basis'=>'SNAT/2013/0029']);
            Policy::validate($p);
            self::assertFalse(Policy::retention(SpeProfiles::profile(),$p,'2026-09-10')['eligible']);
            self::assertTrue(Policy::retention(SpeProfiles::profile(),$p,'2026-09-10',['public_operation_scope'=>'subject','public_scope_reference'=>'DEMO partidas 402'])['eligible']);
            self::assertFalse(Policy::retention(SpeProfiles::profile(),$p,'2026-09-10',['exclusion'=>'DEMO bonos'])['eligible']);
        }
    }
    public function test_payment_uses_actual_currency_intermediation_and_effective_dates(): void
    {
        $p=SpeProfiles::profile();
        foreach (['VES','USD'] as $currency) foreach (['none','financial_institution'] as $intermediation) {
            $decision=Policy::payment($p,['financial_intermediation'=>$intermediation],$currency,'2026-09-10');
            self::assertSame($currency==='USD' && $intermediation==='none'?'subject':'not_applicable',$decision['igtf_status']);
        }
        self::assertFalse(Policy::spe($p,'2025-12-31'));
        $p['revoked_at']='2026-09-10';self::assertFalse(Policy::spe($p,'2026-09-10'));
        self::assertTrue(Policy::spe($p,'2026-09-09'));
        self::assertTrue(Policy::retention($p,$p,'2026-09-10')['islr_eligible']);
    }
    public function test_iva_percentage_requires_a_legal_basis_and_excludes_formal_providers(): void
    {
        $p=SpeProfiles::profile();
        self::assertSame(75,Policy::retention($p,$p,'2026-09-10')['percentage']);
        foreach(Policy::FULL_IVA as $basis) self::assertSame(100,Policy::retention($p,$p,'2026-09-10',['iva_100_basis'=>$basis])['percentage']);
        foreach(['formal','not_subject'] as $status) self::assertFalse(Policy::retention(array_replace($p,['iva_status'=>$status]),$p,'2026-09-10')['eligible']);
        self::assertFalse(Policy::retention(array_replace($p,['iva_exclusion'=>'Acreditada']),$p,'2026-09-10')['eligible']);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        Policy::validate(array_replace($p,['iva_retention_rate'=>100]));
    }
    public function test_inconsistent_classification_cannot_avoid_perception(): void
    {
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        Policy::payment(SpeProfiles::profile(),['financial_intermediation'=>'none','igtf_status'=>'not_applicable'],'USD','2026-09-10');
    }
    public function test_a_non_admin_cannot_authorize_an_exception(): void
    {
        $this->actingAs(new \App\Models\Tenant\User(['id'=>1,'type'=>'seller']));
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        Policy::payment(SpeProfiles::profile(),['financial_intermediation'=>'none','igtf_status'=>'exempt','exemption_reason'=>'Test','exception_basis'=>'Norma demostrativa'],'USD','2026-09-10');
    }
    public function test_islr_authority_is_independent_of_spe(): void
    {
        $p=SpeProfiles::profile(['spe'=>false,'iva_agent_basis'=>'none','islr_agent'=>true]);
        self::assertTrue(Policy::retention($p,$p,'2026-09-10')['islr_eligible']);
        $p['islr_agent']=false;self::assertFalse(Policy::retention($p,$p,'2026-09-10')['islr_eligible']);
    }
}
