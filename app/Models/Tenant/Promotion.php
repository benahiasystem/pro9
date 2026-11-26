<?php

namespace App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;

class Promotion extends ModelTenant
{
   // protected $table = 'pr';
  
    protected $fillable = [
        'type',
        'description',
        'name',
        'status',
        'image',
        'item_id',
        'category_id',
        'custom_link',
        'apply_restaurant',
        'spot_url'
    ];
    
    protected $appends = ['image_url'];

    protected static function boot()
    {
        parent::boot();

        static::addGlobalScope('active', function (Builder $builder) {
            $builder->where('status', 1);
        });
    }

    public function setDescriptionAttribute($value)
    {
        $this->attributes['description'] = !empty($value) ? $value : 'Banner principal';
    }
    
    public function getImageUrlAttribute()
    {
        if ($this->image && $this->image !== 'imagen-no-disponible.jpg') {
            if ($this->apply_restaurant) {
// ########## INICIO MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
                return tenant_asset('storage'.DIRECTORY_SEPARATOR.'uploads'.DIRECTORY_SEPARATOR.'promotions'.DIRECTORY_SEPARATOR.'restaurant'.DIRECTORY_SEPARATOR.$this->image);
// ######### FIN MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
            } else {
// ########## INICIO MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
                return tenant_asset('storage'.DIRECTORY_SEPARATOR.'uploads'.DIRECTORY_SEPARATOR.'promotions'.DIRECTORY_SEPARATOR.$this->image);
// ######### FIN MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
            }
        }
        return asset("/logo/{$this->image}");
    }

    public function item()
    {
        return $this->belongsTo(\App\Models\Tenant\Item::class, 'item_id');
    }

   
}