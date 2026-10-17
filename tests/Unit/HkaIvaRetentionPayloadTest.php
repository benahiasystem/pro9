<?php

namespace Tests\Unit;

use App\Models\Tenant\{Document, DocumentEmission, DocumentReceivedRetention};
use App\Services\Fiscal\HkaIvaRetentionPayload;
use Tests\TestCase;

class HkaIvaRetentionPayloadTest extends TestCase
{
    public function test_mixed_iva_rates_keep_the_full_taxable_base_and_reject_an_ambiguous_partial_base(): void
    {
        $document=new Document;
        $document->setRawAttributes(['total_taxed'=>200,'total_igv'=>24,'currency_type_id'=>'VES','exchange_rate_sale'=>'10.00000000']);
        $reduced=new \App\Models\Tenant\DocumentTax;$reduced->setRawAttributes(['tax_kind'=>'IVA','percentage'=>8,'base'=>100,'amount'=>8]);
        $general=new \App\Models\Tenant\DocumentTax;$general->setRawAttributes(['tax_kind'=>'IVA','percentage'=>16,'base'=>100,'amount'=>16]);
        $document->setRelation('taxes',collect([$reduced,$general]));
        self::assertSame('200',\App\Services\Fiscal\IvaRetentionService::voucherTaxableBase($document,24,'VES',10));
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        \App\Services\Fiscal\IvaRetentionService::voucherTaxableBase($document,8,'VES',10);
    }

    public function test_frozen_invoice_currency_and_taxable_base_are_distinct_from_retained_iva(): void
    {
        $document = new Document;
        $document->setRawAttributes(['control_number'=>'00-00000100','currency_type_id'=>'USD','exchange_rate_sale'=>'10.12345678']);
        $emission = new DocumentEmission;
        $emission->setRawAttributes(['status'=>'confirmed','payload'=>json_encode(['documentoElectronico'=>['encabezado'=>['identificacionDocumento'=>[
            'serie'=>'','tipoDocumento'=>'01','numeroDocumento'=>'100','moneda'=>'VES']]]])]);
        $document->setRelation('emission',$emission);
        $row = new DocumentReceivedRetention;
        $row->setRawAttributes(['voucher_number'=>'20261000000001','voucher_date'=>'2026-10-12','currency_type_id'=>'USD',
            'exchange_rate'=>'10.12345678','taxable_base'=>100,'base'=>16,'amount'=>12]);
        $payload = HkaIvaRetentionPayload::build($document,$row);
        self::assertSame(['serie'=>'','tipoDocumento'=>'01','numeroDocumento'=>'100','numeroControl'=>'00-00000100'],HkaIvaRetentionPayload::identity($payload));
        self::assertSame(['numeroCompRetencion'=>'20261000000001','fechaEmisionCR'=>'12/10/2026','totalBaseImponible'=>'1012.35','totalIVA'=>'161.98','totalRetenido'=>'121.48'],$payload['totalRetencion']);
        self::assertSame('10.12345678',$document->exchange_rate_sale);
        $row->forceFill(['currency_type_id'=>'VES','exchange_rate'=>'20.00000000','taxable_base'=>2000,'base'=>320,'amount'=>240]);
        $vesPayload=HkaIvaRetentionPayload::build($document,$row);
        self::assertSame('2000.00',$vesPayload['totalRetencion']['totalBaseImponible']);
        self::assertSame('320.00',$vesPayload['totalRetencion']['totalIVA']);
        self::assertSame('240.00',$vesPayload['totalRetencion']['totalRetenido']);
        $remote = HkaIvaRetentionPayload::identity($payload)+['totalesRetencion'=>$payload['totalRetencion']];
        self::assertTrue(HkaIvaRetentionPayload::matches($remote,$payload));
        $remote['totalesRetencion']['totalIVA']='16';self::assertFalse(HkaIvaRetentionPayload::matches($remote,$payload));
        $remote['totalesRetencion']=$payload['totalRetencion'];$remote['numeroControl']='00-OTHER';
        self::assertFalse(HkaIvaRetentionPayload::matches($remote,$payload));
    }
}
