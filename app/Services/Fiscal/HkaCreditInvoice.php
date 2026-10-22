<?php
namespace App\Services\Fiscal;

use App\Models\Tenant\Document;
use App\Support\Exact\Decimal as D;

/** A fiscal credit declaration is never a receipt or a settlement of the receivable. */
final class HkaCreditInvoice
{
    public const KEY = 'credit_invoice_contract';
    public const VERSION = 'no_advance_v1';
    public const DESCRIPTION = 'Crédito comercial pendiente de cobro';
    public const UNVERIFIED = 'La emisión de facturas a crédito sin abono espera la validación de su contrato en HKA DEMO. La venta conserva su saldo pendiente; no registre cobros ficticios.';
    public const INELIGIBLE = 'Esta factura cambió y ya no corresponde al contrato HKA DEMO de crédito sin abono. La declaración fiscal del total no se aplicará a abonos, anticipos, retenciones ni fondos de garantía.';
    private const RELATIONS = ['invoice','fiscal_data','payments','taxes','received_retentions','guarantee_fund'];

    public static function eligible(array $d): bool
    {
        if (($d['document_type_id'] ?? '') !== '01' || ($d['payment_condition_id'] ?? '') !== '02'
            || ($d['fiscal_emission_mode'] ?? '') !== 'digital' || ($d['fiscal_environment'] ?? '') !== 'demo'
            || !in_array($d['currency_type_id'] ?? '', ['VES','USD'], true)
            || ($d['invoice']['operation_type_id'] ?? '') !== '0101'
            || ($d['fiscal_data']['transaction_type_id'] ?? '01') !== '01'
            || !empty($d['fiscal_data']['third_party']) || !empty($d['fiscal_data']['special_tax_regime_id'])
            || !empty($d['fiscal_data']['conditional_data']) || !empty($d['prepayments']) || !empty($d['has_prepayment'])
            || !empty($d['received_retentions']) || !empty($d['guarantee_fund'])
            || !empty($d['fiscal_data']['iva_retention_prevision']) || !empty($d['fiscal_data']['islr_retention_prevision'])) return false;
        if (!D::read($d,'total')->isPositive() || !D::read($d,'total_prepayment')->isZero()
            || !D::read($d,'total_exportation')->isZero()) return false;
        foreach ($d['payments'] ?? [] as $p) if (empty($p['reversed_at'])) return false;
        foreach ($d['taxes'] ?? [] as $tax) if (($tax['tax_kind'] ?? '') !== 'IVA') return false;
        return true;
    }

    public static function preload(\Illuminate\Database\Eloquent\Collection $documents): void
    {
        if (!self::verified()) return;
        $documents->filter(fn ($d) => ($d->emission->response[self::KEY] ?? null) === self::VERSION
            && $d->emission->status === 'not_requested' && $d->payment_condition_id === '02')->loadMissing(self::RELATIONS);
    }

    private static function snapshot(Document $document, bool $refresh = false): array
    {
        if ($refresh) $document->load(self::RELATIONS);
        else $document->loadMissing(self::RELATIONS);
        // Read only eligibility inputs; serializing every attribute also evaluates unrelated edit-policy queries.
        $d = $document->only(['document_type_id','payment_condition_id','fiscal_emission_mode','fiscal_environment',
            'currency_type_id','total','total_prepayment','total_exportation','prepayments','has_prepayment','calculation_snapshot']);
        foreach (self::RELATIONS as $relation) {
            $value = $document->getRelation($relation);
            $d[$relation] = $value ? $value->toArray() : null;
        }
        return $d;
    }

    /** Called only on the trusted WEB creation path, after commercial payments/settlements. */
    public static function captureCreation(Document $document, bool $webDocuments): void
    {
        if (!$webDocuments || $document->document_type_id !== '01' || $document->payment_condition_id !== '02'
            || $document->fiscal_emission_mode !== 'digital' || $document->fiscal_environment !== 'demo'
            || !self::eligible(self::snapshot($document, true))) return;
        $emission = $document->emission()->firstOrFail();
        $emission->update(['response'=>array_replace($emission->response ?? [], [self::KEY=>self::VERSION])]);
    }

    public static function verified(): bool
    {
        return config('hka_credit_invoices.demo_no_advance_verified', false) === true;
    }

    public static function reason(Document $document): ?string
    {
        $emission = $document->emission;
        if (($emission->response[self::KEY] ?? null) !== self::VERSION || $emission->status !== 'not_requested'
            || $document->payment_condition_id !== '02') return null;
        if (!self::verified()) return self::UNVERIFIED;
        return self::eligible(self::snapshot($document)) ? null : self::INELIGIBLE;
    }

    /** Append after currency adaptation, so the declaration exactly matches HKA's principal total. */
    public static function append(array $payload, array $snapshot): array
    {
        if (($snapshot[self::KEY] ?? null) !== self::VERSION || ($snapshot['payment_condition_id'] ?? '') !== '02') return $payload;
        if (!self::verified()) FiscalAmounts::error('credit_invoice', self::UNVERIFIED);
        if (!self::eligible($snapshot)) FiscalAmounts::error('credit_invoice', self::INELIGIBLE);
        $header = &$payload['documentoElectronico']['encabezado'];
        if (!empty($header['totales']['formasPago'])) FiscalAmounts::error('credit_invoice', self::INELIGIBLE);
        $header['totales']['formasPago'] = [['forma'=>'99', 'descripcion'=>self::DESCRIPTION,
            'moneda'=>$header['identificacionDocumento']['moneda'], 'monto'=>$header['totales']['totalAPagar'],
            'fecha'=>\Carbon\Carbon::parse($snapshot['date_of_issue'])->format('d/m/Y'),
            'tipoCambio'=>(string)$snapshot['exchange_rate_sale']]];
        return $payload;
    }
}
