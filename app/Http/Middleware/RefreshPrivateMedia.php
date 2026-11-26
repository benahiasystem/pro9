<?php
// ########## INICIO MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
namespace App\Http\Middleware;

use App\Models\Tenant\Item;
use App\Services\Storage\{ItemMedia,TenantStorage};
use Closure;
use Illuminate\Http\JsonResponse;

/** Cache stable references; issue capabilities only at the HTTP response boundary. */
final class RefreshPrivateMedia
{
    public function handle($request,Closure $next)
    {
        $response=$next($request);
        if(!$response instanceof JsonResponse || $response->getStatusCode()>=400 || !app(\App\Tenancy\Environment::class)->tenant())return $response;
        $data=$response->getData(true);
        $names=[];
        $filename=function($value){
            if(!is_string($value))return null;
            $path=parse_url($value,PHP_URL_PATH);
            $prefix='/media/images/items/';
            if(!is_string($path)||!str_starts_with($path,$prefix))return null;
            $name=rawurldecode(substr($path,strlen($prefix)));
            return $name!=='' && basename($name)===$name?$name:null;
        };
        $collect=function($value)use(&$collect,&$names,$filename){
            if(!is_array($value))return;
            foreach($value as $key=>$entry){
                if(is_array($entry))$collect($entry);
                elseif(in_array($key,['image_url','image_url_medium','image_url_small'],true) && ($name=$filename($entry)))$names[$name]=true;
            }
        };
        $collect($data);
        if(!$names)return $response;
        $allowed=[];
        $guest=!ItemMedia::employee();
        $channelAllowed=!$guest || (ItemMedia::ecommerceEnabled() && !optional(\App\Models\Tenant\Configuration::first())->isLockedTenant());
        foreach($channelAllowed?array_chunk(array_keys($names),300):[] as $chunk){
            $query=Item::withoutEagerLoads()->where(fn($q)=>$q->whereIn('image',$chunk)->orWhereIn('image_medium',$chunk)->orWhereIn('image_small',$chunk));
            if(!ItemMedia::employee())$query->where('active',1)->where('apply_store',1);
            foreach($query->get(['id','image','image_medium','image_small']) as $item){
                foreach(['image','image_medium','image_small'] as $field)if($item->$field)$allowed[$item->$field]=$item->id;
            }
        }
        $paths=array_map(fn($name)=>'uploads/items/'.$name,array_keys(array_intersect_key($allowed,$names)));
        $links=$paths?app(TenantStorage::class)->disk()->temporaryUrls($paths):['urls'=>[],'expires_at'=>null];
        $refresh=function($value)use(&$refresh,$allowed,$filename,$links){
            if(!is_array($value))return $value;
            foreach($value as $key=>$entry){
                if(is_array($entry))$value[$key]=$refresh($entry);
                elseif(in_array($key,['image_url','image_url_medium','image_url_small'],true) && ($name=$filename($entry))){
                    $url=$links['urls']['uploads/items/'.$name]??null;
                    $value[$key]=$url??asset('logo/imagen-no-disponible.jpg');
                    if($url){
                        $value['image_media_id']=$allowed[$name];
                        $value['image_urls_expires_at']=$links['expires_at'];
                    }
                }
            }
            return $value;
        };
        $response->headers->set('Cache-Control','private, no-store');
        return $response->setData($refresh($data));
    }
}
// ######### FIN MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
