<?php
namespace App\Http\Controllers\Tenant;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Dispatch;
use App\Services\Fiscal\{DeliveryOrderInput,HkaDispatchEmission,HkaDispatchPdf,HkaDispatchRelations,DocumentFileName};

final class DispatchFiscalController extends Controller
{
    public function send($id) { $d=DeliveryOrderInput::order($id);abort_unless(auth()->user()->type==='admin',403);return ['success'=>true,'fiscal_emission'=>app(HkaDispatchEmission::class)->send($d)]; }
    public function query($id) { $d=DeliveryOrderInput::order($id);return ['success'=>true,'fiscal_emission'=>app(HkaDispatchEmission::class)->query($d)]; }
    public function relate($id) { $d=DeliveryOrderInput::order($id);abort_unless(auth()->user()->type==='admin',403);return ['success'=>true,'hka_relations'=>app(HkaDispatchRelations::class)->sync($d)]; }
    public function download($external_id)
    {
        DeliveryOrderInput::authorize();$d=Dispatch::where('external_id',$external_id)->firstOrFail();DeliveryOrderInput::authorize((int)$d->establishment_id);
        return response(app(HkaDispatchPdf::class)->download($d),200,['Content-Type'=>'application/pdf','Content-Disposition'=>'attachment; filename="'.DocumentFileName::visible($d->filename).'.pdf"','Cache-Control'=>'private, no-store','X-Content-Type-Options'=>'nosniff']);
    }
}
