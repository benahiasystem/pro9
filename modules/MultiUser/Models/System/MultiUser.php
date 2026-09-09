<?php

namespace Modules\MultiUser\Models\System;

use App\Models\System\Client;


class MultiUser extends ModelSystem
{

    protected $fillable = [
        'origin_client_id',
        'destination_client_id',
        'origin_user_id',
        'destination_user_id',
        'email',
        'user',
    ];

    protected $casts = [
    ];


    public function origin_client()
    {
        return $this->belongsTo(Client::class, 'origin_client_id');
    }

    public function destination_client()
    {
        return $this->belongsTo(Client::class, 'destination_client_id');
    }

    public function getUserAttribute($value)
    {
        return (is_null($value)) ? null : (object)json_decode($value);
    }

    public function setUserAttribute($value)
    {
        $this->attributes['user'] = (is_null($value)) ? null : json_encode($value);
    }


    /**
     *
     * Filtros listado
     *
     * @param  Builder $query
     * @param  Request $request
     * @return Builder
     */
    public function scopeFilterRecords($query, $request)
    {
        $this->scopeWithClientData($query);

        return $this->scopeApplySearch($query, $request);
    }


    /**
     *
     * Datos base del vínculo con sus empresas
     *
     * @param  Builder $query
     * @return Builder
     */
    public function scopeWithClientData($query)
    {
        return $query->select([
            'id',
            'destination_client_id',
            'origin_client_id',
            'origin_user_id',
            'destination_user_id',
            'email',
            'user',
        ])
        ->with([
            'destination_client' => function($destination_client){
                $destination_client->filterDataMultiUser();
            },
            'origin_client' => function($origin_client){
                $origin_client->filterDataMultiUser();
            }
        ]);
    }


    /**
     *
     * @param  Builder $query
     * @param  Request $request
     * @return Builder
     */
    public function scopeFilterGroupedRecords($query, $request)
    {
        $query->selectRaw('MIN(id) as id, MAX(id) as last_id, origin_client_id, origin_user_id')
                ->groupBy('origin_client_id', 'origin_user_id')
                ->orderByDesc('last_id');

        return $this->scopeApplySearch($query, $request);
    }


    /**
     *
     * Filtro de búsqueda del listado
     *
     * @param  Builder $query
     * @param  Request $request
     * @return Builder
     */
    public function scopeApplySearch($query, $request)
    {
        if(!empty($request->value))
        {
            $value = $request->value;

            if(in_array($request->column, ['origin_client', 'destination_client']))
            {
                $query->whereHas($request->column, function($client) use($value){
                    $client->where(function($q) use($value){
                        $q->where('name', 'like', "%{$value}%")
                            ->orWhere('number', 'like', "%{$value}%");
                    });
                });
            }
            else
            {
                $query->where('email', 'like', "%{$value}%");
            }
        }

        return $query;
    }

}
