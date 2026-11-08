<?php

namespace Modules\Hotel\Services;

use App\Models\Tenant\Document;
use App\Models\Tenant\SaleNote;
use App\Services\Payments\Classification;
use App\Support\Exact\Persistence;
use Illuminate\Database\Eloquent\Model;
use Modules\Hotel\Models\HotelRent;

/** Serialize every checkout-document producer on the same hotel stay. */
final class CheckoutCreation
{
    public static function assertLinkUnchanged(Model $document): void
    {
        if (!$document->getRawOriginal('hotel_rent_id') && !$document->hotel_rent_id) return;
        if ($document->isDirty('hotel_rent_id') || $document->isDirty('source_module')) {
            Classification::error('checkout', 'El vínculo de una estancia no puede cambiarse al editar el comprobante.');
        }
    }

    public static function assertAllowed(Model $document): void
    {
        if (!$document->hotel_rent_id) return;
        if ($document->source_module !== 'HOTEL') {
            Classification::error('checkout', 'Una estancia sólo puede vincularse a un comprobante de Hotel.');
        }
        if ($document instanceof Document && $document->document_type_id !== '01') return;
        $prototype = new HotelRent();
        if (!Persistence::enabled($prototype)) return;
        $connection = $prototype->getConnection();
        if ($connection->transactionLevel() < 1) {
            Classification::error('checkout', 'El comprobante de Hotel debe registrarse en una transacción.');
        }
        $rent = HotelRent::query()->lockForUpdate()->find($document->hotel_rent_id);
        if (!$rent || $rent->status === 'FINALIZADO') {
            Classification::error('checkout', 'La estancia no existe o ya fue finalizada.');
        }
        if ((int) $rent->establishment_id !== (int) $document->establishment_id
            || (int) $rent->customer_id !== (int) $document->customer_id) {
            Classification::error('checkout', 'El cliente y la sucursal deben coincidir con la estancia.');
        }
        $note = SaleNote::withoutEagerLoads()->where('source_module','HOTEL')->where('hotel_rent_id',$rent->id)
            ->whereNotIn('state_type_id',SaleNote::VOIDED_REJECTED_IDS)->lockForUpdate()->first();
        $invoice = Document::withoutEagerLoads()->where('source_module','HOTEL')->where('hotel_rent_id',$rent->id)
            ->where('document_type_id','01')->whereNotIn('state_type_id',Document::VOIDED_REJECTED_IDS)->lockForUpdate()->first();
        if ($note || $invoice) {
            Classification::error('checkout', 'Ya existe un comprobante vigente para esta estancia; revíselo antes de crear otro.');
        }
    }
}
