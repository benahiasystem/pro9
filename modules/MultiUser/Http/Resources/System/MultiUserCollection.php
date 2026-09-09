<?php

namespace Modules\MultiUser\Http\Resources\System;

use Illuminate\Http\Resources\Json\ResourceCollection;
use App\Models\Tenant\User;


class MultiUserCollection extends ResourceCollection
{

    public function toArray($request)
    {
        return $this->collection->transform(function($row, $key){

            $origin = $this->parseClient($row->origin_client);
            $origin_type = $row->type;

            $links = collect($row->links)->map(function($link) use($origin_type){

                $destination = $this->parseClient($link->destination_client);

                return [
                    'id' => $link->id,
                    'full_name' => $destination['full_name'],
                    'name' => $destination['name'],
                    'number' => $destination['number'],
                    'hostname' => $destination['hostname'],
                    'missing' => $destination['missing'],
                    'type' => $link->current_type ?? $origin_type,
                    'description_type' => User::getDescriptionType($link->current_type ?? $origin_type),
                ];

            })->values();

            return [
                'id' => $row->id,
                'composed_id' => $row->composed_id,
                'user_name' => $row->user->name ?? '-',
                'user_full_name' => $row->email,
                'type' => $origin_type,
                'description_type' => User::getDescriptionType($origin_type),
                'client_origin_full_name' => $origin['full_name'],
                'origin_name' => $origin['name'],
                'origin_number' => $origin['number'],
                'origin_hostname' => $origin['hostname'],
                'origin_missing' => $origin['missing'],
                'links' => $links,
                'links_count' => $links->count(),
            ];

        });
    }


    /**
     *
     * Datos de la empresa, contemplando clientes eliminados
     *
     * @param  Client|null $client
     * @return array
     */
    private function parseClient($client)
    {
        if(!$client)
        {
            return [
                'full_name' => 'Cliente eliminado',
                'name' => 'Cliente eliminado',
                'number' => null,
                'hostname' => 'Subdominio no encontrado',
                'missing' => true,
            ];
        }

        return [
            'full_name' => $client->getFullName(),
            'name' => $client->name,
            'number' => $client->number,
            'hostname' => optional($client->hostname)->fqdn ?? 'Subdominio no encontrado',
            'missing' => false,
        ];
    }

}
