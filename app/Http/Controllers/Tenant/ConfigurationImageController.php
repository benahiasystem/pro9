<?php
// ########## INICIO MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
namespace App\Http\Controllers\Tenant;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Storage\TenantStorage;
use App\Models\Tenant\{Configuration,User};
use Modules\Finance\Helpers\UploadFileHelper;
final class ConfigurationImageController extends Controller {
    public function upload(Request $request) {
        abort_unless($request->user()?->isAdmin(), 403);
        abort_unless(auth()->user() instanceof User && auth()->user()->type==='admin',403);
        $request->validate(['image'=>'required|file|mimes:jpeg,png,gif,webp,avif|max:3072']);
        $file=$request->file('image');
        $name=\Illuminate\Support\Str::uuid().'.'.$file->extension();
        UploadFileHelper::checkIfValidFile($name,$file->getRealPath(),true);
        return tenant_storage_transaction(function()use($file,$name){
            $configuration=Configuration::lockForUpdate()->firstOrFail();
            app(TenantStorage::class)->disk()->putFileAs('uploads/defaults',$file,$name);
            $old=$configuration->product_default_image;
            $configuration->product_default_image=$name;$configuration->save();
            if($old && $old!==$name)app(TenantStorage::class)->disk()->delete('uploads/defaults/'.basename($old));
            return response()->json(['message'=>'Imagen subida correctamente.','file'=>$name,'url'=>tenant_asset('uploads/defaults/'.$name)]);
        });
    }
}
// ######### FIN MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
