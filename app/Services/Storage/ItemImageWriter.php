<?php
// ########## INICIO MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
namespace App\Services\Storage;

use App\Models\Tenant\{Item,ItemImage};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Intervention\Image\ImageManagerStatic as Image;
use Modules\Finance\Helpers\UploadFileHelper;

final class ItemImageWriter
{
    /** Assign images to the model within the caller's tenant transaction. */
    public function replace(Item $item, string $temporary): void
    {
        $extension = $this->extension($temporary);
        $name = Str::limit(Str::slug($item->internal_id ?: $item->description), 20, '').'-'.Str::uuid();
        UploadFileHelper::checkIfValidFile($name.'.'.$extension, $temporary, true);

        $disk = app(TenantStorage::class)->disk();
        $variants = ['image'=>$name.'.'.$extension, 'image_medium'=>$name.'_medium.jpg', 'image_small'=>$name.'_small.jpg'];
        $stream = fopen($temporary, 'rb');
        if (!$stream) throw new \RuntimeException('No se pudo leer la imagen.');
        try {
            $disk->put('uploads/items/'.$variants['image'], $stream);
        } finally {
            fclose($stream);
        }

        foreach (['image_medium'=>[512,30], 'image_small'=>[256,20]] as $field=>[$width,$quality]) {
            $image = Image::make($temporary);
            $image->resize($width, null, function ($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
            });
            $disk->put('uploads/items/'.$variants[$field], (string) $image->encode('jpg', $quality));
        }

        $previous = array_unique(array_filter(array_map(fn($field)=>$item->getOriginal($field), array_keys($variants))));
        $item->fill($variants);
        foreach ($previous as $filename) {
            if ($filename === 'imagen-no-disponible.jpg' || basename($filename) !== $filename) continue;
            $otherItem = Item::withoutGlobalScopes()->whereKeyNot($item->id)->where(fn($query)=>
                $query->where('image',$filename)->orWhere('image_medium',$filename)->orWhere('image_small',$filename)
            )->exists();
            if (!$otherItem && !ItemImage::where('image',$filename)->exists()) $disk->delete('uploads/items/'.$filename);
        }
    }

    public function additional(string $temporary): string
    {
        $filename = Str::uuid().'.'.$this->extension($temporary);
        UploadFileHelper::checkIfValidFile($filename, $temporary, true);
        app(TenantStorage::class)->disk()->put('uploads/items/'.$filename, file_get_contents($temporary));
        return $filename;
    }

    private function extension(string $temporary): string
    {
        return match (mime_content_type($temporary)) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            default => throw ValidationException::withMessages(['image'=>'Formato de imagen no permitido.']),
        };
    }
}
// ######### FIN MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
