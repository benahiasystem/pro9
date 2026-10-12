<?php

namespace App\Services;

use App\Models\Tenant\{Company, Document, Person};
use App\Services\Fiscal\FiscalAmounts;
use App\Support\System\Rif;

/** Web-only third-party sales; the server owns the fiscal classification and snapshot. */
final class ThirdPartyInvoicePolicy
{
    public static function isThirdParty($document): bool
    {
        return ($document->fiscal_data->transaction_type_id ?? '01') === '99';
    }

    public static function requested(array $input): bool
    {
        return isset($input['third_party_id']) && $input['third_party_id'] !== '';
    }

    public static function view(Document $document): array
    {
        return ['is_third_party_invoice' => self::isThirdParty($document),
            'third_party_id' => $document->fiscal_data->third_party_person_id ?? null,
            'third_party' => $document->fiscal_data->third_party ?? null];
    }

    public static function assertInput(array $input, bool $web = false): void
    {
        $fiscal = $input['fiscal_data'] ?? [];
        if (!is_array($fiscal)) FiscalAmounts::error('fiscal_data', 'Los datos fiscales requieren un objeto válido.');
        if (!empty($input['third_party']) || !empty($fiscal['third_party']) || !empty($fiscal['third_party_person_id']) || ($fiscal['transaction_type_id'] ?? '01') === '99') {
            FiscalAmounts::error('fiscal_data', 'Seleccione el cliente tercero; sus datos fiscales los determina el servidor.');
        }
        if (in_array((string) ($input['document_type_id'] ?? ''), ['07', '08'], true)) {
            $affected = Document::find($input['note']['affected_document_id'] ?? $input['affected_document_id'] ?? 0);
            if ($affected && self::isThirdParty($affected)) FiscalAmounts::error('note', 'Las notas de facturas por cuenta de terceros todavía no están habilitadas.');
        }
        if (!self::requested($input)) return;
        if (!$web || (isset($input['source_module']) && $input['source_module'] !== 'WEB')) FiscalAmounts::error('third_party_id', 'La facturación por cuenta de terceros está disponible únicamente desde Comprobantes web.');
        validator($input, ['third_party_id' => 'required|integer|min:1'])->validate();
        if (($input['document_type_id'] ?? '') !== '01' || ExportInvoicePolicy::operation($input) !== '0101') FiscalAmounts::error('third_party_id', 'La facturación por cuenta de terceros requiere una Factura de venta interna.');
        if (!ExportInvoicePolicy::available(Company::query()->setEagerLoads([])->firstOrFail())) FiscalAmounts::error('third_party_id', 'La facturación por cuenta de terceros HKA requiere Medios digitales y DEMO.');
        if (($input['payment_condition_id'] ?? '') !== '01') FiscalAmounts::error('payment_condition_id', 'Esta etapa de facturación por terceros admite únicamente Contado.');
        if (!empty($input['is_itinerant']) || (isset($input['itinerant']['id']) && (string) $input['itinerant']['id'] !== '1') || !empty($fiscal['special_tax_regime_id']) || !empty($fiscal['conditional_data']) || ($fiscal['transaction_type_id'] ?? '01') !== '01') FiscalAmounts::error('fiscal_data', 'La factura por terceros no admite regímenes ni otras operaciones especiales.');
    }

    public static function assertSameCategory(Document $document, array $input): void
    {
        if (self::isThirdParty($document) !== self::requested($input)) FiscalAmounts::error('third_party_id', 'No se puede convertir una factura registrada entre venta propia y por cuenta de terceros.');
    }

    public static function resolveInput(array $input, bool $web): array
    {
        self::assertInput($input, $web);
        if (!self::requested($input)) return $input;
        $query = Person::query()->setEagerLoads([])->whereType('customers')->where('enabled', true)->where('is_third_party', true);
        if (auth()->user()) $query->whereFilterCustomerBySeller('customers');
        $person = $query->lockForUpdate()->find($input['third_party_id']);
        if (!$person) FiscalAmounts::error('third_party_id', 'Seleccione un cliente habilitado y clasificado como tercero.');
        $snapshot = [
            'person_id' => $person->id, 'rif' => $person->third_party_rif,
            'name' => $person->name, 'address' => $person->address,
            'country_id' => $person->country_id, 'activity' => $person->third_party_activity,
            'email' => $person->email,
        ];
        self::hkaThirdParty($snapshot);
        $input['fiscal_data'] = array_replace($input['fiscal_data'] ?? [], [
            'transaction_type_id' => '99', 'third_party_person_id' => $person->id, 'third_party' => $snapshot,
        ]);
        return $input;
    }

    /** Pure translation from the frozen client data to HKA's Tercero model. */
    public static function hkaThirdParty(array $snapshot): array
    {
        $rif = $snapshot['rif'] ?? '';
        validator(['rif' => $rif], ['rif' => 'required|string|regex:/^[VEJPG][0-9]{9}$/D'])->validate();
        if (!Rif::isValid($rif) || $rif !== Rif::normalize($rif)) FiscalAmounts::error('third_party_rif', 'El tercero requiere un RIF fiscal completo conservado y válido.');
        validator($snapshot, [
            'country_id' => 'required|in:VE', 'name' => 'required|string|max:100',
            'address' => 'required|string|max:255', 'activity' => 'required|string|max:100',
            'email' => 'nullable|email|max:50',
        ])->validate();
        $result = ['tipoIdentificacion' => $rif[0],
            'numeroIdentificacion' => $rif[0].'-'.substr($rif, 1, 8).'-'.substr($rif, -1),
            'razonSocial' => $snapshot['name'], 'direccion' => $snapshot['address'], 'tipo' => $snapshot['activity']];
        if (!empty($snapshot['email'])) $result['correo'] = [$snapshot['email']];
        return $result;
    }

    public static function assertPaid(Document $document): void
    {
        if (!self::isThirdParty($document)) return;
        if ($document->payment_condition_id !== '01' || abs((float) $document->fresh()->balance) > 0.005
            || abs((float) $document->payments()->whereNull('reversed_at')->sum('payment') - (float) $document->total) > 0.005
            || !$document->payments()->whereNull('reversed_at')->whereNull('receipt_parent_id')->where('payment', '>', 0)->exists()) {
            FiscalAmounts::error('payments', 'La factura por cuenta de terceros debe estar completamente pagada al contado con cobros reales.');
        }
    }
}
