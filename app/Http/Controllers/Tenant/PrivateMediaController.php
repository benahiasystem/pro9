<?php
// ########## INICIO MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\{Company, Item, Promotion};
use App\Services\Storage\{ItemMedia,TenantDisk,TenantStorage};
use Illuminate\Http\Request;

final class PrivateMediaController extends Controller
{
    public function brand(string $slot)
    {
        abort_unless(in_array($slot,['logo','logo_dark','app_logo','favicon'],true),404);
        $company = Company::firstOrFail();
        $name = $company->$slot;
        abort_unless(is_string($name) && $name!=='',404);
        $path = $slot==='favicon' ? TenantDisk::relative($name) : 'uploads/logos/'.basename($name);
        abort_unless(str_starts_with($path,$slot==='favicon'?'uploads/favicons/':'uploads/logos/'),404);
        return $this->imageResponse($path);
    }

    public function image(string $folder, string $filename)
    {
        abort_unless(preg_match('/\A[a-zA-Z0-9_-]+\z/D',$folder) && basename($filename)===$filename,404);
        if ($folder === 'items' && $filename === 'imagen-no-disponible.jpg') return redirect(asset('logo/imagen-no-disponible.jpg'));
        $path = TenantDisk::relative('uploads/'.$folder.'/'.$filename);
        if (!ItemMedia::employee()) {
            $allowed = false;
            if(in_array($folder,['items','categories','defaults','promotions'],true))abort_if(optional(\App\Models\Tenant\Configuration::first())->isLockedTenant(),403);
            if(in_array($folder,['items','categories','defaults','promotions'],true))abort_unless(ItemMedia::ecommerceEnabled(),404);
            if ($folder==='items') {
                $allowed=Item::where('active',1)->where('apply_store',1)
                    ->where(fn($q)=>$q->where('image',$filename)->orWhere('image_medium',$filename)->orWhere('image_small',$filename)->orWhereHas('images',fn($images)=>$images->where('image',$filename)))->exists();
            } elseif ($folder==='logos') {
                $company=Company::first();
                $allowed=$company && in_array($filename,[$company->logo,$company->logo_dark,$company->app_logo],true);
                if(!$allowed && ItemMedia::ecommerceEnabled())$allowed=optional(\App\Models\Tenant\ConfigurationEcommerce::first())->logo===$filename;
            } elseif ($folder==='favicons') {
                $allowed=basename((string)optional(Company::first())->favicon)===$filename;
            } elseif ($folder==='categories') {
                $allowed=\Modules\Item\Models\Category::where('image',$filename)->exists();
            } elseif ($folder==='login') {
                $global=\App\Models\System\Configuration::first();
                $login=optional(\App\Models\Tenant\Configuration::first())->login;
                $expected=is_object($login)?($login->image??null):($login['image']??null);
                $allowed=$global && !$global->use_login_global && $this->matchesReference($expected,'login',$filename);
            } elseif ($folder==='public-search-backgrounds') {
                $allowed=optional(\App\Models\Tenant\Configuration::first())->public_search_bg_image_path==='uploads/public-search-backgrounds/'.$filename;
            } elseif ($folder==='defaults') {
                $allowed=optional(\App\Models\Tenant\Configuration::first())->product_default_image===$filename;
            } elseif ($folder==='promotions') {
                $allowed=Promotion::where('image',$filename)->where('status',1)->where('apply_restaurant',0)
                    ->where(fn($q)=>$q->whereNull('type')->orWhere('type','banners'))->exists();
            }
            abort_unless($allowed,404);
        }
        abort_if(in_array($folder,['firms'],true) && !ItemMedia::employee(),404);
        return $this->imageResponse($path);
    }

    public function skin(int $id)
    {
        $skin=\App\Models\Tenant\Skin::findOrFail($id);
        abort_if($skin->is_system,404);
        if(!ItemMedia::employee())abort_unless((int)optional(\App\Models\Tenant\Configuration::first())->skin_id===$id,404);
        return app(TenantStorage::class)->disk()->download('uploads/skins/'.$skin->filename,$skin->filename,['Content-Type'=>'text/css; charset=UTF-8'],'inline');
    }

    public function restaurantImage(string $folder,string $filename)
    {
        abort_unless(in_array($folder,['items','promotions'],true) && basename($filename)===$filename,404);
        if($folder==='items')$allowed=Item::where('active',1)->where('apply_restaurant',1)
            ->where(fn($q)=>$q->where('image',$filename)->orWhere('image_medium',$filename)->orWhere('image_small',$filename)->orWhereHas('images',fn($images)=>$images->where('image',$filename)))->exists();
        else $allowed=Promotion::where('image',$filename)->where('status',1)->where('apply_restaurant',1)
            ->where(fn($q)=>$q->whereNull('type')->orWhere('type','!=','spots'))->exists();
        abort_unless($allowed,404);
        return $this->imageResponse('uploads/'.$folder.($folder==='promotions'?'/restaurant':'').'/'.$filename);
    }

    private function matchesReference($reference,string $folder,string $filename): bool
    {
        if(!is_string($reference) || str_contains($reference,'%'))return false;
        if(preg_match('#^https?://#i',$reference)){
            if(parse_url($reference,PHP_URL_HOST)!==request()->getHost())return false;
            $reference=ltrim(parse_url($reference,PHP_URL_PATH)??'','/');
        }
        return in_array($reference,['storage/uploads/'.$folder.'/'.$filename,'uploads/'.$folder.'/'.$filename,'media/images/'.$folder.'/'.$filename],true);
    }

    public function items(Request $request)
    {
        $data=$request->validate(['ids'=>'required|array|max:100','ids.*'=>'required|integer|min:1']);
        return ['data'=>app(ItemMedia::class)->batch($data['ids'],!ItemMedia::employee())];
    }

    private function imageResponse(string $path)
    {
        try {
            $disk=app(TenantStorage::class)->disk();
            abort_unless($disk->exists($path),404);
            $mime=$disk->mimeType($path);
            abort_unless(in_array($mime,['image/jpeg','image/png','image/gif','image/webp','image/avif','image/svg+xml','image/x-icon','image/vnd.microsoft.icon'],true),404);
            return $disk->download($path,basename($path),[
                'Content-Type'=>$mime,
                'Content-Security-Policy'=>"default-src 'none'; style-src 'unsafe-inline'",
            ],'inline');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $error) { throw $error; }
        catch (\Throwable $error) { abort(503,'La imagen no está disponible temporalmente.'); }
    }
}
// ######### FIN MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
