<?php
namespace Tests\Support;

/** Synthetic, explicit DEMO facts. Never seed fiscal qualification into live tenants. */
final class SpeProfiles
{
    public static function profile(array $changes = []): array
    {
        return array_replace(['rif'=>'J-12345678-9','nature'=>'legal','personal_firm'=>false,'public_kind'=>null,
            'commercial_activity'=>true,'iva_status'=>'ordinary','spe'=>true,
            'notification_reference'=>'DEMO-SENIAT-SYNTHETIC','notification_date'=>'2026-01-01','effective_from'=>'2026-01-01',
            'effective_until'=>null,'revoked_at'=>null,'iva_agent_basis'=>'SNAT/2025/000054','islr_agent'=>true,
            'islr_basis'=>'DEMO-concept-review','iva_retention_rate'=>75,'iva_100_basis'=>null,'iva_exclusion'=>null,'evidence_reference'=>'DEMO-private-evidence'], $changes);
    }

    public static function versions($db, int $companyId, int $personId): void
    {
        if (!preg_match('/^pro9_fiscal_test_[a-f0-9]{12}$/D', $db->getDatabaseName())) throw new \RuntimeException('Temporary fiscal database required');
        $company = $db->table('companies')->find($companyId);
        $rif = preg_replace('/[^A-Z0-9]/','',strtoupper($company->number));
        $rif = substr($rif,0,1).'-'.substr($rif,1,8).'-'.substr($rif,-1);
        foreach (['company_id'=>[$companyId, self::profile(['rif'=>$rif])], 'person_id'=>[$personId, self::profile(['rif'=>'V-12345678-9','nature'=>'natural','personal_firm'=>true])]] as $key=>$values) {
            $db->table('fiscal_profile_versions')->insert([$key=>$values[0],'effective_from'=>'2026-01-01','profile'=>json_encode($values[1]),'actor_id'=>$db->table('users')->value('id')]);
        }
    }

    public static function voucher($document, array $changes = []): array
    {
        $attachment = 'received_retentions/'.$document->id.'/demo.pdf';
        \Illuminate\Support\Facades\Storage::disk('tenant')->put($attachment, '%PDF-DEMO-private-support');
        $buyer = \App\Services\Fiscal\FiscalProfileService::at(\App\Models\Tenant\Person::findOrFail($document->customer_id), '2026-09-10');
        return array_replace(['operation_key'=>(string)\Illuminate\Support\Str::uuid(),'attachment'=>$attachment,'agent_rif'=>$buyer['rif'] ?? '',
            'fiscal_context'=>['concept_basis'=>'DEMO revisión de concepto recibido','evidence_reference'=>'DEMO soporte recibido'], 'invoice_control'=>$document->control_number,'invoice_number'=>$document->number_full],$changes);
    }
}
