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
        ], [
            'required'=>'Complete el campo «:attribute».',
            'string'=>'El campo «:attribute» debe contener texto.',
            'boolean'=>'Indique si corresponde «:attribute».',
            'in'=>'Seleccione una opción válida para «:attribute».',
            'max'=>'El campo «:attribute» admite hasta :max caracteres.',
            'date_format'=>'Seleccione una fecha válida para «:attribute».',
            'after_or_equal'=>'La fecha de «:attribute» debe ser igual o posterior a «Vigencia desde».',
            'rif.regex'=>'Escriba el RIF con una letra, ocho dígitos y un dígito final, separados por guiones. Ejemplo: J-12345678-9.',
        ], [
            'rif'=>'RIF completo', 'nature'=>'Naturaleza jurídica', 'personal_firm'=>'Constituida como firma personal',
            'public_kind'=>'Clase de ente público', 'commercial_activity'=>'Actividad comercial habilitada',
            'iva_status'=>'Condición frente al IVA', 'spe'=>'Sujeto Pasivo Especial',
            'notification_reference'=>'Referencia de notificación SENIAT', 'notification_date'=>'Fecha de notificación',
            'effective_from'=>'Vigencia desde', 'effective_until'=>'Vigencia hasta', 'revoked_at'=>'Revocación SPE desde',
            'iva_agent_basis'=>'Facultad de agente IVA', 'islr_agent'=>'Facultad ISLR acreditada', 'islr_basis'=>'Fundamento ISLR',
            'iva_retention_rate'=>'Retención IVA aplicable al proveedor', 'iva_100_basis'=>'Fundamento del 100 %',
            'iva_exclusion'=>'Exclusión IVA acreditada', 'evidence_reference'=>'Referencia de evidencia fiscal',
        ])->validate();
        foreach (['personal_firm','commercial_activity','spe','islr_agent'] as $key) $p[$key] = (bool)$p[$key];
        $errors = [];
        $prefix = substr($p['rif'], 0, 1);
        if (($p['nature'] === 'natural' && !in_array($prefix, ['V','E','R'], true))
            || ($p['nature'] === 'legal' && !in_array($prefix, ['J','C'], true))
            || ($p['nature'] === 'public' && !in_array($prefix, ['G','J'], true))) {
            $allowed = ['natural'=>'V, E o R', 'legal'=>'J o C', 'public'=>'G o J'];
            $errors['rif'] = 'El RIF debe comenzar por '.$allowed[$p['nature']].' para la naturaleza seleccionada. Revise el RIF y la naturaleza jurídica.';
        }
        if ($p['nature'] !== 'natural' && $p['personal_firm']) $errors['nature'] = 'El perfil conserva una firma personal. Seleccione Persona natural o desmarque la firma personal antes de cambiar la naturaleza.';
        if (($p['nature'] === 'public') !== !empty($p['public_kind'])) {
            $errors[$p['nature'] === 'public' ? 'public_kind' : 'nature'] = $p['nature'] === 'public'
                ? 'Seleccione la clase de órgano o ente público.' : 'El perfil conserva una clase de ente público. Revise la naturaleza jurídica y la clase seleccionada.';
        }
        if ($p['spe']) {
            if (empty($p['notification_reference'])) $errors['notification_reference'] = 'Escriba la referencia de la notificación del SENIAT que acredita la condición SPE.';
            if (empty($p['notification_date'])) $errors['notification_date'] = 'Seleccione la fecha de la notificación del SENIAT.';
            elseif ($p['notification_date'] > $p['effective_from']) $errors['notification_date'] = 'La fecha de notificación no puede ser posterior a «Vigencia desde». Revise ambas fechas.';
        }
        if ($p['iva_agent_basis'] === 'SNAT/2025/000054') {
            if (!$p['spe']) $errors['iva_agent_basis'] = 'Seleccionó SNAT/2025/000054, pero no está marcada la condición SPE. Seleccione «Sin facultad acreditada» si no dispone de esa facultad; si está acreditada, complete la condición SPE y su notificación.';
            elseif ($p['nature'] === 'natural' && !$p['personal_firm']) $errors['iva_agent_basis'] = 'Esta facultad exige que la persona natural esté constituida como firma personal. Revise su documentación o seleccione «Sin facultad acreditada».';
            elseif (self::publicSpecific($p)) $errors['iva_agent_basis'] = 'Esta facultad no corresponde a la clase de ente público seleccionada. Revise la facultad acreditada y la clase de ente público.';
        }
        if ($p['iva_agent_basis'] === 'SNAT/2013/0029') {
            if ($p['nature'] !== 'public') $errors['iva_agent_basis'] = 'La opción SNAT/2013/0029 corresponde a órganos o entes públicos. Revise la naturaleza jurídica o seleccione otra facultad acreditada.';
            if (empty($p['evidence_reference'])) $errors['evidence_reference'] = 'Escriba la referencia del documento que acredita la facultad de agente IVA del ente público.';
        }
        if ($p['islr_agent'] && empty($p['islr_basis'])) $errors['islr_basis'] = 'Escriba el fundamento que acredita la facultad ISLR. Si no está acreditada, desmarque «Facultad ISLR acreditada».';
        if ((int)$p['iva_retention_rate'] === 100) {
            if (empty($p['iva_100_basis'])) $errors['iva_100_basis'] = 'Seleccione la causal que justifica la retención del 100 %.';
            if (empty($p['evidence_reference'])) $errors['evidence_reference'] = 'Escriba la referencia del documento que sustenta la retención del 100 %.';
        }
        if ($errors) throw \Illuminate\Validation\ValidationException::withMessages($errors);
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
        // ######## INICIO RETIRADA DE EXCEPCION ADMINISTRATIVA IGTF ########
        RetiredIgtfException::assertNew($input);
        // ######## FIN RETIRADA DE EXCEPCION ADMINISTRATIVA IGTF ########
        $intermediation = $input['financial_intermediation'] ?? null;
        if (!in_array($intermediation, [null, 'none', 'financial_institution'], true)) FiscalAmounts::error('financial_intermediation', 'Seleccione la intermediación real del cobro.');
        if ($currency === 'USD' && self::spe($issuer, $date) && $intermediation === null) FiscalAmounts::error('financial_intermediation', 'Indique si el cobro en divisas tiene intermediación financiera.');
        $subject = self::spe($issuer, $date) && $currency === 'USD' && $intermediation === 'none';
        $status = $subject ? 'subject' : 'not_applicable';
        if (isset($input['igtf_status']) && $input['igtf_status'] !== $status) {
            FiscalAmounts::error('igtf_status', 'La clasificación IGTF no coincide con el perfil vigente y el cobro real.');
        }
        return ['igtf_status'=>$status, 'financial_intermediation'=>$intermediation];
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
