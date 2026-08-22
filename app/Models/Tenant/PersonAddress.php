<?php

namespace App\Models\Tenant;

use App\Models\Tenant\Catalogs\Department;
use App\Models\Tenant\Catalogs\District;
use App\Models\Tenant\Catalogs\Province;
use App\Models\Tenant\Catalogs\Country;


/**
 * App\Models\Tenant\PersonAddress
 *
 * @property-read Country $country
 * @property-read Department $department
 * @property-read District $district
 * @property-read mixed $address_full
 * @property mixed $location_id
 * @property-read Province $province
 * @method static \Illuminate\Database\Eloquent\Builder|PersonAddress newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|PersonAddress newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|PersonAddress query()
 * @mixin \Eloquent
 */
class PersonAddress extends ModelTenant
{
    protected $table = 'person_addresses';
    protected $with = [];
    public $timestamps = false;
    protected $fillable = [
        'person_id',
        'country_id',
        'department_id',
        'province_id',
        'district_id',
        'address',
        'location_id',
        'phone',
        'email',
        'main',
        'establishment_code',
        'has_consigned',
        'consigned_id',
    ];

    protected $casts = [
        'has_consigned' => 'bool'
    ];
    /**
     * Retorna un standar de nomenclatura para el modelo
     *
     * @return array
     */
    public function getCollectionData() {
        $consigned = Consigned::find($this->consigned_id);
        return [
            'id' => $this->id,
            'trade_name' => $this->trade_name,
            'country_id' => $this->country_id,
            'location_id' => $this->getResolvedLocationId(),
            'address' => $this->address,
            'phone' => $this->phone,
            'email' => $this->email,
            'main' => (bool)$this->main,

            'department_id' => $this->department_id,
            'province_id' => $this->province_id,
            'district_id' => $this->district_id,
            'establishment_code' => $this->establishment_code,
            'has_consigned' => (bool)$this->has_consigned,
            'consigned_id' => $this->consigned_id,
            'consigned_name' => $consigned ? $consigned->name : null,

        ];
    }
    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function province()
    {
        return $this->belongsTo(Province::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function district()
    {
        return $this->belongsTo(District::class);
    }

    public function setLocationIdAttribute($value)
    {
        if (!is_array($value) || count($value) !== 3) {
            $this->attributes['department_id'] = null;
            $this->attributes['province_id'] = null;
            $this->attributes['district_id'] = null;
            return;
        }

        if (empty($value[0]) || empty($value[1]) || empty($value[2])) {
            $this->attributes['department_id'] = null;
            $this->attributes['province_id'] = null;
            $this->attributes['district_id'] = null;
            return;
        }

        $this->attributes['department_id'] = $value[0];
        $this->attributes['province_id'] = $value[1];
        $this->attributes['district_id'] = $value[2];
    }

    public function getLocationIdAttribute()
    {
        return $this->getResolvedLocationId();
    }

    /**
     * Devuelve un arreglo ubigeo válido [departamento, provincia, distrito] o vacío.
     */
    public function getResolvedLocationId(): array
    {
        if (!$this->department_id || !$this->province_id || !$this->district_id) {
            return [];
        }

        return [
            $this->department_id,
            $this->province_id,
            $this->district_id,
        ];
    }

    public function getAddressFullAttribute()
    {
        $address = trim($this->address);
        $address = ($address === '-' || $address === '')?'':$address.' ,';
        if ($address === '') {
            return '';
        }
        return "{$address} {$this->department->description} - {$this->province->description} - {$this->district->description}";
    }
}
