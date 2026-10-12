<?php
namespace App\Services\Fiscal;

/** SPE, IVA and retention authority are independent, dated fiscal facts. */
final class SpecialTaxpayerPolicy
{
    public const PUBLIC_KINDS = ['national', 'state', 'municipal', 'autonomous_institute', 'decentralized_non_business', 'decentralized_business'];
    public const FULL_IVA = ['undisclosed_iva', 'invoice_formalities', 'portal_100', 'unregistered_rif', 'precious_metals'];

    public static function validate(array $input): array
    {
        FiscalDocumentPersistence::assertNoSecrets($input);
        $p = validator($input, [
            'rif'=>'required|string|regex:/^[VJEGCR]-[0-9]{8}-[0-9]$/D',
            'nature'=>'required|in:natural,legal,public', 'personal_firm'=>'required|boolean',
            'public_kind'=>'nullable|in:'.implode(',', self::PUBLIC_KINDS), 'commercial_activity'=>'required|boolean',
            'iva_status'=>'required|in:ordinary,formal,not_subject', 'spe'=>'required|boolean',
            'notification_reference'=>'nullable|string|max:255', 'notification_date'=>'nullable|date_format:Y-m-d',
            'effective_from'=>'required|date_format:Y-m-d', 'effective_until'=>'nullable|date_format:Y-m-d|after_or_equal:effective_from',
            'revoked_at'=>'nullable|date_format:Y-m-d|after_or_equal:effective_from',
            'iva_agent_basis'=>'required|in:none,SNAT/2025/000054,SNAT/2013/0029',
            'islr_agent'=>'required|boolean', 'islr_basis'=>'nullable|string|max:255',
            'iva_retention_rate'=>'required|in:75,100', 'iva_100_basis'=>'nullable|in:'.implode(',', self::FULL_IVA),
            'iva_exclusion'=>'nullable|string|max:255', 'evidence_reference'=>'nullable|string|max:255',
        ])->validate();
        foreach (['personal_firm','commercial_activity','spe','islr_agent'] as $key) $p[$key] = (bool)$p[$key];
        $prefix = substr($p['rif'], 0, 1);
        if (($p['nature'] === 'natural' && !in_array($prefix, ['V','E','R'], true))
            || ($p['nature'] === 'legal' && !in_array($prefix, ['J','C'], true))
            || ($p['nature'] === 'public' && !in_array($prefix, ['G','J'], true))) FiscalAmounts::error('rif', 'El RIF fiscal debe corresponder a la naturaleza declarada; la cédula permanece separada.');
        if ($p['nature'] !== 'natural' && $p['personal_firm']) FiscalAmounts::error('personal_firm', 'La firma personal corresponde a una persona natural.');
        if (($p['nature'] === 'public') !== !empty($p['public_kind'])) FiscalAmounts::error('public_kind', 'Indique la clase del ente público únicamente para esa naturaleza.');
        if ($p['spe'] && (empty($p['notification_reference']) || empty($p['notification_date']) || $p['notification_date'] > $p['effective_from'])) FiscalAmounts::error('notification_reference', 'SPE requiere notificación expresa del SENIAT y fecha anterior o igual a su vigencia.');
        if ($p['iva_agent_basis'] === 'SNAT/2025/000054' && (!$p['spe'] || ($p['nature'] === 'natural' && !$p['personal_firm']) || self::publicSpecific($p))) FiscalAmounts::error('iva_agent_basis', 'Este perfil no acredita la facultad de agente IVA bajo SNAT/2025/000054.');
        if ($p['iva_agent_basis'] === 'SNAT/2013/0029' && ($p['nature'] !== 'public' || empty($p['evidence_reference']))) FiscalAmounts::error('iva_agent_basis', 'La facultad pública requiere naturaleza pública y referencia de su fundamento.');
        if ($p['islr_agent'] && empty($p['islr_basis'])) FiscalAmounts::error('islr_basis', 'Indique el fundamento independiente de la facultad ISLR.');
        if ((int)$p['iva_retention_rate'] === 100 && (empty($p['iva_100_basis']) || empty($p['evidence_reference']))) FiscalAmounts::error('iva_100_basis', 'El 100 % requiere fundamento y referencia de evidencia.');
        return $p;
    }

    private static function publicSpecific(array $p): bool
    {
        return ($p['nature'] ?? '') === 'public' && ($p['public_kind'] ?? '') !== 'decentralized_business';
    }

    public static function active(array $p, string $date): bool
    {
        return !empty($p['effective_from']) && $p['effective_from'] <= $date
            && (empty($p['effective_until']) || $p['effective_until'] >= $date);
    }

    public static function spe(array $p, string $date): bool
    {
        return self::active($p, $date) && !empty($p['spe']) && !empty($p['notification_reference'])
            && (empty($p['revoked_at']) || $p['revoked_at'] > $date)
            && !empty($p['notification_date']) && $p['notification_date'] <= $date;
    }

    public static function payment(array $issuer, array $input, string $currency, string $date): array
    {
        $intermediation = $input['financial_intermediation'] ?? null;
        if (!in_array($intermediation, [null, 'none', 'financial_institution'], true)) FiscalAmounts::error('financial_intermediation', 'Seleccione la intermediación real del cobro.');
        if ($currency === 'USD' && self::spe($issuer, $date) && $intermediation === null) FiscalAmounts::error('financial_intermediation', 'Indique si el cobro en divisas tiene intermediación financiera.');
        $subject = self::spe($issuer, $date) && $currency === 'USD' && $intermediation === 'none';
        $status = $subject ? 'subject' : 'not_applicable';
        if (($input['igtf_status'] ?? '') === 'exempt') {
            if (empty($input['exemption_reason']) || empty($input['exception_basis'])) FiscalAmounts::error('exception_basis', 'Una excepción requiere motivo y fundamento.');
            abort_unless(auth()->user() instanceof \App\Models\Tenant\User && auth()->user()->type === 'admin', 403);
            $status = 'exempt';
        } elseif (isset($input['igtf_status']) && $input['igtf_status'] !== $status) {
            FiscalAmounts::error('igtf_status', 'La clasificación IGTF no coincide con el perfil vigente y el cobro real.');
        }
        return ['igtf_status'=>$status, 'financial_intermediation'=>$intermediation,
            'exception_basis'=>$status === 'exempt' ? $input['exception_basis'] : null,
            'authorized_by'=>$status === 'exempt' ? auth()->id() : null];
    }

    public static function retention(array $issuer, array $buyer, string $date, array $context = []): array
    {
        $basis = $buyer['iva_agent_basis'] ?? 'none';
        $general = $basis === 'SNAT/2025/000054' && self::spe($buyer, $date)
            && (($buyer['nature'] ?? '') !== 'natural' || !empty($buyer['personal_firm'])) && !self::publicSpecific($buyer);
        $public = $basis === 'SNAT/2013/0029' && ($buyer['nature'] ?? '') === 'public' && !empty($buyer['evidence_reference']);
        $eligible = self::active($buyer, $date) && ($general || $public)
            && ($issuer['iva_status'] ?? '') === 'ordinary' && empty($issuer['iva_exclusion']);
        $exclusion = $context['exclusion'] ?? null;
        if ($exclusion) $eligible = false;
        // Public acquisitions require a documented budget/operation review, never inferred from SPE.
        if ($public && (($context['public_operation_scope'] ?? '') !== 'subject' || empty($context['public_scope_reference']))) $eligible = false;
        $full = $context['iva_100_basis'] ?? ((int)($issuer['iva_retention_rate'] ?? 75) === 100 ? ($issuer['iva_100_basis'] ?? null) : null);
        if ($full && !in_array($full, self::FULL_IVA, true)) FiscalAmounts::error('iva_100_basis', 'Fundamento de retención del 100 % inválido.');
        $rate = $full ? 100 : 75;
        if ($public && $full === 'precious_metals') FiscalAmounts::error('iva_100_basis', 'Revise el fundamento bajo la normativa específica del ente público.');
        return ['eligible'=>$eligible, 'percentage'=>$eligible ? $rate : 0, 'basis'=>$basis,
            'iva_100_basis'=>$full, 'exclusion'=>$exclusion ?: ($issuer['iva_exclusion'] ?? null),
            'islr_eligible'=>self::active($buyer, $date) && !empty($buyer['islr_agent']) && !empty($buyer['islr_basis'])];
    }

    public static function forPrint($document): array
    {
        if (!$document instanceof \App\Models\Tenant\Document) return [];
        $date = $document->date_of_issue->format('Y-m-d');
        $rows = [];
        foreach (['issuer'=>'Emisor','customer'=>'Comprador'] as $key=>$role) {
            $snapshot = (array)$document->$key;
            $profile = (array)($snapshot['fiscal_profile'] ?? []);
            if (!empty($profile['rif'])) $rows[] = ['role'=>$role,'rif'=>$profile['rif'],'spe'=>self::spe($profile,$date)];
        }
        return $rows;
    }
}
