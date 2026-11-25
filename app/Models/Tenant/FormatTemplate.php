<?php

namespace App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use App\Tenancy\Traits\UsesTenantConnection;

class FormatTemplate extends ModelTenant
{
    use UsesTenantConnection;

    protected $fillable = [
    	'id',
    	'formats',
        'urls',
        'is_custom_ticket'
    ];

    public function getUrlAttribute($value)
    {
        return (is_null($value))?null:(object) json_decode($value);
    }

    public function setUrlAttribute($value)
    {
        $this->attributes['urls'] = (is_null($value))?null:json_encode($value);
    }

    public function getCollectionData()
    {
        $urls = json_decode($this->urls);
        $previews = [
            'templates/pdf/default/image.png',
            'templates/pdf/modern-2027/image.png',
            'templates/pdf/Plantilla_personalizable/image.png',
            'templates/pdf/marca_de_agua/image.png',
        ];
        $preview = $urls->invoice ?? null;
        if (in_array($preview, $previews, true) && is_file(public_path($preview))) {
            $urls->invoice .= '?v=' . hash_file('sha256', public_path($preview));
        }

        $data = [
            'id' => $this->id,
            'name' => $this->formats,
            'urls' => $urls,
        ];
        return $data;
    }
}
