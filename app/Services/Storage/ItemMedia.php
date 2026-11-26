<?php
// ########## INICIO MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
namespace App\Services\Storage;

use App\Models\Tenant\Item;
use App\Models\Tenant\User;

final class ItemMedia
{
    public static function employee(): bool
    {
        $user = auth()->user() ?? auth('api')->user();
        return $user instanceof User && $user->isActive();
    }

    public static function ecommerceEnabled(): bool
    {
        $owner=User::withoutEagerLoads()->orderBy('id')->first();
        return $owner && $owner->modules()->where('value','ecommerce')->exists();
    }

    public static function visibleToGuest(Item $item): bool
    {
        return self::ecommerceEnabled() && (bool) $item->active && (bool) $item->apply_store;
    }

    public function url(Item $item, string $variant = 'original'): string
    {
        $field = ['original'=>'image','medium'=>'image_medium','small'=>'image_small'][$variant] ?? null;
        if (!$field) throw new \InvalidArgumentException('Variante de imagen inválida.');
        $name = $item->$field;
        if (!$name || $name === 'imagen-no-disponible.jpg') return asset('logo/imagen-no-disponible.jpg');
        if (!app()->runningInConsole() && !self::employee() && !self::visibleToGuest($item)) return asset('logo/imagen-no-disponible.jpg');
        if (basename(str_replace('\\','/',$name)) !== $name) throw new \InvalidArgumentException('Nombre de imagen inválido.');
        return tenant_asset('uploads/items/'.$name);
    }

    public function batch(array $ids, bool $guest = false): array
    {
        if (count($ids)>100) abort(422,'Solicite hasta cien productos por lote.');
        if (!$guest) abort_unless(self::employee(),403);
        $query = Item::withoutEagerLoads()->whereIn('id',$ids);
        if ($guest) abort_unless(self::ecommerceEnabled(),404);
        if ($guest) $query->where('active',1)->where('apply_store',1);
        return $query->get()->map(fn(Item $item)=>[
            'id'=>$item->id,'image_media_id'=>$item->id,'image_url'=>$this->url($item),'image_url_medium'=>$this->url($item,'medium'),
            'image_url_small'=>$this->url($item,'small'),
            'image_urls_expires_at'=>now()->addSeconds(config('tenant_storage.signed_url_ttl',300))->toIso8601String(),
        ])->values()->all();
    }
}
// ######### FIN MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
