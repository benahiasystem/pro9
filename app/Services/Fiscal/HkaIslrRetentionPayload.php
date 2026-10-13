<?php
namespace App\Services\Fiscal;
use App\Models\Tenant\{Document,DocumentReceivedRetention};
use App\Services\ExchangeRates\ExchangeRateMath;
final class HkaIslrRetentionPayload
{
    public const VERIFIED_FIELDS = ['serie','tipoDocumento','numeroDocumento','numeroControl','numeroCompRetencion','fechaEmisionCR','impuestoRetenido'];
    public static function identity(array $payload): array { return HkaIvaRetentionPayload::identity($payload); }
    public static function build(Document $document, DocumentReceivedRetention $retention): array
    {
        $id = $document->emission->payload['documentoElectronico']['encabezado']['identificacionDocumento'] ?? [];
        if (($id['tipoDocumento'] ?? '') !== '01' || !$document->control_number || $document->emission->status !== 'confirmed') FiscalAmounts::error('document','La retención espera una factura HKA confirmada con control real.');
        if (!in_array($id['moneda'] ?? null,['VES','USD'],true)) FiscalAmounts::error('currency_type_id','Falta la moneda fiscal conservada en HKA.');
        return ['serie'=>$id['serie'] ?? '', 'tipoDocumento'=>'01','numeroDocumento'=>(string)$id['numeroDocumento'],'numeroControl'=>$document->control_number,
            'detallesRetencionISLR'=>['numeroCompRetencion'=>$retention->voucher_number,'fechaEmisionCR'=>$retention->voucher_date->format('d/m/Y'),
                'impuestoRetenido'=>ExchangeRateMath::finalAmount(FiscalAmounts::convert($retention->amount,$retention->currency_type_id,$id['moneda'],$retention->exchange_rate)),
                'rifAgenteRetencion'=>$retention->agent_number,'porcentaje'=>ExchangeRateMath::finalAmount($retention->percentage)]];
        // codigoOperacion is deliberately omitted: HKA documents no mapping to concept_id.
    }
}
