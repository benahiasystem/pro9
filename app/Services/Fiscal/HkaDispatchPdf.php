<?php
namespace App\Services\Fiscal;
use App\Models\Tenant\{Company,Dispatch};
use Illuminate\Support\Facades\Storage;

final class HkaDispatchPdf
{
    public static function view(Dispatch $d): array
    {
        $ready=optional(HkaDispatchEmission::record($d))->status==='confirmed' && !in_array($d->state_type_id,['09','11'],true);
        return ['hka_a4'=>['provider'=>'HKA','available'=>$ready,'url'=>$ready ? url('dispatches/'.$d->external_id.'/download-hka-pdf/a4'):null]];
    }

    public function download(Dispatch $order,bool $authorize=true): string
    {
        if ($authorize) DeliveryOrderInput::authorize((int)$order->establishment_id);
        if ($order->getConnection()->transactionLevel()) FiscalAmounts::error('pdf','Descargue el PDF después de guardar la orden.');
        $order=$order->fresh();$company=app(HkaDispatchEmission::class)->company($order);$e=$order->emission;
        if ($e->status!=='confirmed' || !$order->control_number || $order->control_number!==$e->control_number) FiscalAmounts::error('pdf','El PDF HKA requiere una orden confirmada con control coherente.');
        $key=$e->operation_key;$identity=$e->payload['documentoElectronico']['encabezado']['identificacionDocumento'];
        if (HkaDispatchEmission::context($company)!==($e->response['context'] ?? null)) FiscalAmounts::error('pdf','La conexión HKA no corresponde a la operación.');
        $path='pdf/hka-dispatch/'.$key.'/a4.pdf';$disk=Storage::disk('tenant');
        if ($disk->exists($path)) { try { return HkaPdf::validatePdf($disk->get($path)); } catch (\Throwable $ignored) {} }
        if (HkaDispatchEmission::context($company)!==($e->response['context'] ?? null)) FiscalAmounts::error('pdf','La conexión HKA no corresponde a la operación.');
        $transport=app(HkaTransport::class);$token=$transport->token($company);
        $current=$order->fresh();if ($authorize) DeliveryOrderInput::authorize((int)$current->establishment_id);
        if (HkaDispatchEmission::context(app(HkaDispatchEmission::class)->company($current))!==($e->response['context'] ?? null)) FiscalAmounts::error('pdf','La conexión cambió durante la descarga.');
        try { $response=$transport->downloadPdf($token,$identity);$bytes=HkaPdf::decode($response->status(),$response->json()); }
        catch (\Throwable $error) { FiscalAmounts::error('pdf','No se pudo obtener el PDF original HKA. Puede reintentar la descarga.'); }
        $order->getConnection()->transaction(function() use($order,$key,$bytes,$path,$disk,$authorize) {
            Company::lockForUpdate()->firstOrFail();$d=Dispatch::lockForUpdate()->findOrFail($order->id);if ($authorize) DeliveryOrderInput::authorize((int)$d->establishment_id);app(HkaDispatchEmission::class)->company($d);
            $e=$d->emission()->lockForUpdate()->firstOrFail();if ($e->operation_key!==$key || $e->status!=='confirmed' || HkaDispatchEmission::context(Company::firstOrFail())!==($e->response['context'] ?? null)) FiscalAmounts::error('pdf','La operación cambió durante la descarga.');
            $disk->makeDirectory(dirname($path));$temporary=$path.'.'.bin2hex(random_bytes(6)).'.tmp';
            try { $disk->put($temporary,$bytes);if (!rename($disk->path($temporary),$disk->path($path))) throw new \RuntimeException('PDF storage'); }
            finally { if ($disk->exists($temporary)) $disk->delete($temporary); }
            $e->update(['file_path'=>$path]);
        });
        return $bytes;
    }
}
